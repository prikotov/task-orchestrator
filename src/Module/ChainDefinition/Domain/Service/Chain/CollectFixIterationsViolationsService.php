<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain;

use Override;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainConfigViolationVo;

/**
 * Реализация коллектора detailed-нарушений ссылочной целостности fix-итераций.
 *
 * Stateless, без I/O: работает только с domain VO. Тело метода перенесено дословно
 * из прежнего inline-цикла ChainDefinitionValidatorService — форматы сообщений и
 * порядок нарушений сохранены побайтово (покрыто существующими тестами валидатора
 * и антидивергентным датапровайдером).
 *
 * Синхронизировано с {@see \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Specification\Chain\FixIterationsReferenceIntegritySpecification}:
 * для одного шага сначала диагностируется unknown step, затем duplicate-membership;
 * unknown-шаг в аккумулятор stepFirstGroup не попадает (повторяет short-circuit спецификации).
 */
final readonly class CollectFixIterationsViolationsService implements CollectFixIterationsViolationsServiceInterface
{
    #[Override]
    public function collect(string $chainName, array $steps, array $fixIterations): array
    {
        if ($fixIterations === []) {
            return [];
        }

        // Карта именованных шагов строится один раз до цикла по группам:
        // она нужна и для unknown-проверки, и для duplicate-проверки.
        $stepNameMap = [];
        foreach ($steps as $step) {
            $stepName = $step->getName();
            if ($stepName !== null) {
                $stepNameMap[$stepName] = true;
            }
        }

        // Имя шага → имя первой группы fix-итерации, в которой он встретился.
        // Используется для диагностики принадлежности шага нескольким группам.
        $stepFirstGroup = [];

        $violations = [];
        foreach ($fixIterations as $group) {
            foreach ($group->getStepNames() as $stepName) {
                if (!isset($stepNameMap[$stepName])) {
                    $violations[] = new ChainConfigViolationVo(
                        chainName: $chainName,
                        field: 'fix_iterations',
                        message: sprintf(
                            'fix_iteration group "%s" references unknown step "%s".',
                            $group->getGroup(),
                            $stepName,
                        ),
                    );

                    continue;
                }

                if (isset($stepFirstGroup[$stepName])) {
                    $violations[] = new ChainConfigViolationVo(
                        chainName: $chainName,
                        field: 'fix_iterations',
                        message: sprintf(
                            'fix_iteration step "%s" belongs to multiple groups ("%s" and "%s").',
                            $stepName,
                            $stepFirstGroup[$stepName],
                            $group->getGroup(),
                        ),
                    );

                    continue;
                }

                $stepFirstGroup[$stepName] = $group->getGroup();
            }
        }

        return $violations;
    }
}
