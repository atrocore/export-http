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

namespace ExportHttp\TwigFunction;

use Espo\ORM\Entity;
use Pim\Entities\Category;

class CategoryFromChannel extends AbstractTwigFunction
{
    public function __construct()
    {
        parent::__construct();

        $this->addDependency('serviceFactory');
    }

    public function run($category, $channelId): bool
    {
        if (empty($category) || !($category instanceof Category) || empty($channelId)) {
            return false;
        }

        $channel = $this->getInjection('serviceFactory')->create('Channel')->getEntity($channelId);
        if (empty($channel)) {
            return false;
        }

        $rootsIds = array_column($channel->get('categories')->toArray(), 'id');

        return in_array($this->getCategoryRoot($category)->get('id'), $rootsIds);
    }

    protected function getCategoryRoot(Entity $category): Entity
    {
        if (empty($parent = $category->get('categoryParent'))) {
            return $category;
        }

        return $this->getCategoryRoot($parent);
    }
}
