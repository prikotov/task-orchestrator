<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Application\Exception;

use RuntimeException;

/**
 * Application-уровневая ошибка получения installation token.
 *
 * Boundary-контракт: наружу из use case {@see \TaskOrchestrator\Common\Module\GitIdentity\Application\UseCase\Command\ObtainToken\ObtainTokenCommandHandler}
 * выбрасываются только Application-исключения. Domain-исключения модуля
 * (GitIdentityException и его наследники) перехватываются в handler'е и
 * оборачиваются в {@see ObtainTokenFailedException} с сохранением `previous`,
 * что позволяет слою Presentation (CLI-команда) ловить единый тип без
 * зависимости от Domain — в соответствии с конвенцией исключений
 * («наружу выбрасываются только исключения слоя»).
 *
 * Сообщения наследуются от оборачиваемого Domain-исключения и не содержат
 * секретов (PEM/JWT/token) согласно контракту C модуля.
 */
final class ObtainTokenFailedException extends RuntimeException
{
}
