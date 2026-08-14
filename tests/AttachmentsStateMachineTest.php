<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Tests;

use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Classify;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\PropertyTesting\StateMachine\CommandSequence;
use Rasuvaeff\PropertyTesting\StateMachine\StateMachine;
use Rasuvaeff\Yii3FilestorageAttachments\Tests\Support\AttachmentCommand;
use Rasuvaeff\Yii3FilestorageAttachments\Tests\Support\AttachmentCommandType;
use Rasuvaeff\Yii3FilestorageAttachments\Tests\Support\AttachmentModel;
use Rasuvaeff\Yii3FilestorageAttachments\Tests\Support\AttachmentStateMachineHarness;
use Testo\Assert\ExpectNoAssertions;
use Testo\Codecov\CoversNothing;
use Testo\Test;

#[Test]
#[CoversNothing]
final class AttachmentsStateMachineTest
{
    #[ExpectNoAssertions]
    #[Property(runs: 80)]
    public function operationSequencesMatchTheModel(CommandSequence $sequence): void
    {
        $harness = null;

        try {
            StateMachine::check($sequence, static function () use (&$harness): AttachmentStateMachineHarness {
                return $harness = new AttachmentStateMachineHarness();
            });
        } finally {
            $harness?->close();
        }

        $this->classifySequence($sequence);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function operationSequencesMatchTheModelGenerators(): array
    {
        $ownerType = Gen::elements(AttachmentStateMachineHarness::OWNER_TYPES);
        $ownerId = Gen::elements(AttachmentStateMachineHarness::OWNER_IDS);
        $role = Gen::elements(AttachmentStateMachineHarness::ROLES);
        $fileId = Gen::elements(AttachmentStateMachineHarness::FILE_IDS);

        return [
            'sequence' => Gen::commands(
                new AttachmentModel(),
                [
                    Gen::map(
                        Gen::tuple($ownerType, $ownerId, $role, $fileId),
                        static fn(array $parts): AttachmentCommand => AttachmentCommand::attach(
                            (string) $parts[0],
                            (string) $parts[1],
                            (string) $parts[2],
                            (string) $parts[3],
                        ),
                    ),
                    Gen::map(
                        Gen::tuple($ownerType, $ownerId, $role, $fileId),
                        static fn(array $parts): AttachmentCommand => AttachmentCommand::detach(
                            (string) $parts[0],
                            (string) $parts[1],
                            (string) $parts[2],
                            (string) $parts[3],
                        ),
                    ),
                    Gen::map(
                        Gen::tuple($ownerType, $ownerId),
                        static fn(array $parts): AttachmentCommand => AttachmentCommand::detachOwner(
                            (string) $parts[0],
                            (string) $parts[1],
                        ),
                    ),
                    Gen::map(
                        Gen::tuple($ownerType, $ownerId, Gen::nullable($role)),
                        static fn(array $parts): AttachmentCommand => AttachmentCommand::listOwner(
                            (string) $parts[0],
                            (string) $parts[1],
                            is_string($parts[2]) ? $parts[2] : null,
                        ),
                    ),
                ],
                minLength: 8,
                maxLength: 30,
            ),
        ];
    }

    /** @return iterable<string, array{CommandSequence}> */
    public static function operationSequencesMatchTheModelExamples(): iterable
    {
        $link = AttachmentCommand::attach('order', 'owner-1', 'default', 'file-1');

        yield 'duplicate attach remains a single link' => [new CommandSequence(
            new AttachmentModel(),
            [$link, $link, AttachmentCommand::listOwner('order', 'owner-1', null)],
        )];

        yield 'detach owner removes every role but not another owner' => [new CommandSequence(
            new AttachmentModel(),
            [
                AttachmentCommand::attach('order', 'owner-1', 'default', 'file-1'),
                AttachmentCommand::attach('order', 'owner-1', 'preview', 'file-1'),
                AttachmentCommand::attach('order', 'owner-2', 'default', 'file-1'),
                AttachmentCommand::detachOwner('order', 'owner-1'),
                AttachmentCommand::listOwner('order', 'owner-1', null),
                AttachmentCommand::listOwner('order', 'owner-2', 'default'),
            ],
        )];
    }

    private function classifySequence(CommandSequence $sequence): void
    {
        $model = $sequence->initialModel;
        \assert($model instanceof AttachmentModel);
        $duplicateAttach = false;
        $missingDetach = false;
        $ownerCleanup = false;
        $filteredList = false;

        foreach ($sequence->commands as $command) {
            \assert($command instanceof AttachmentCommand);
            $next = $command->nextState($model);

            $duplicateAttach = $duplicateAttach || ($command->type === AttachmentCommandType::Attach && $next === $model);
            $missingDetach = $missingDetach || ($command->type === AttachmentCommandType::Detach && $next === $model);
            $ownerCleanup = $ownerCleanup || ($command->type === AttachmentCommandType::DetachOwner && $next !== $model);
            $filteredList = $filteredList || ($command->type === AttachmentCommandType::ListOwner && $command->role !== null);
            $model = $next;
        }

        Classify::cover($duplicateAttach, 'duplicate attach', 3.0);
        Classify::cover($missingDetach, 'missing detach', 3.0);
        Classify::cover($ownerCleanup, 'owner cleanup', 3.0);
        Classify::cover($filteredList, 'role-filtered list', 3.0);
    }
}
