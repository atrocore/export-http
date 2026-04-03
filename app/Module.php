<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace ExportHttp;

use Atro\Core\EntityTypeHandlers\CreateLinkHandler;
use Atro\Core\EntityTypeHandlers\FollowHandler;
use Atro\Core\EntityTypeHandlers\GetDuplicateAttributesHandler;
use Atro\Core\EntityTypeHandlers\ListHandler;
use Atro\Core\EntityTypeHandlers\ListLinkedHandler;
use Atro\Core\EntityTypeHandlers\MassDeleteHandler;
use Atro\Core\EntityTypeHandlers\MassFollowHandler;
use Atro\Core\EntityTypeHandlers\MassUnfollowHandler;
use Atro\Core\EntityTypeHandlers\MassUpdateHandler;
use Atro\Core\EntityTypeHandlers\MergeHandler;
use Atro\Core\EntityTypeHandlers\RemoveLinkHandler;
use Atro\Core\EntityTypeHandlers\UnfollowHandler;
use Atro\Core\ModuleManager\AbstractModule;

class Module extends AbstractModule
{
    public static function getLoadOrder(): int
    {
        return 5150;
    }

    public function getEntityTypeHandlerExcludes(): array
    {
        return [
            // ExportHttpHeader — managed exclusively via ExportFeed; direct CRUD is not allowed
            ListHandler::class                   => ['ExportHttpHeader'],
            ListLinkedHandler::class             => ['ExportHttpHeader'],
            MassUpdateHandler::class             => ['ExportHttpHeader'],
            MassDeleteHandler::class             => ['ExportHttpHeader'],
            CreateLinkHandler::class             => ['ExportHttpHeader'],
            RemoveLinkHandler::class             => ['ExportHttpHeader'],
            FollowHandler::class                 => ['ExportHttpHeader'],
            UnfollowHandler::class               => ['ExportHttpHeader'],
            MergeHandler::class                  => ['ExportHttpHeader'],
            GetDuplicateAttributesHandler::class => ['ExportHttpHeader'],
            MassFollowHandler::class             => ['ExportHttpHeader'],
            MassUnfollowHandler::class           => ['ExportHttpHeader'],
        ];
    }
}
