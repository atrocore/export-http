<?php
/*
 * This file is part of premium software, which is NOT free.
 * Copyright (c) AtroCore UG (haftungsbeschränkt).
 *
 * This Software is the property of AtroCore UG (haftungsbeschränkt) and is
 * protected by copyright law - it is NOT Freeware and can be used only in one
 * project under a proprietary license, which is delivered along with this program.
 * If not, see <https://atropim.com/eula> or <https://atrodam.com/eula>.
 *
 * This Software is distributed as is, with LIMITED WARRANTY AND LIABILITY.
 * Any unauthorised use of this Software without a valid license is
 * a violation of the License Agreement.
 *
 * According to the terms of the license you shall not resell, sublicense,
 * rent, lease, distribute or otherwise transfer rights or usage of this
 * Software or its derivatives. You may modify the code of this Software
 * for your own needs, if source code is provided.
 */

declare(strict_types=1);

namespace ExportHttp\TwigFunction;

use Pim\Entities\Category;

class PreviousCategoryId extends AbstractTwigFunction
{
    public function __construct()
    {
        $this->addDependency('entityManager');
        $this->addDependency('serviceFactory');
    }

    public function run($category)
    {
        if (empty($category) || !($category instanceof Category)) {
            return null;
        }

        $where = !empty($this->getFeedData()['data']['where']) ? $this->getFeedData()['data']['where'] : [];

        if (empty($category->get('categoryParentId'))) {
            $where[] = [
                'type'      => 'isNull',
                'attribute' => 'categoryParentId'
            ];
        } else {
            $where[] = [
                'type'      => 'equals',
                'attribute' => 'categoryParentId',
                'value'     => $category->get('categoryParentId')
            ];
        }

        $categories = $this
            ->getInjection('serviceFactory')
            ->create('Category')
            ->findEntities(['where' => $where, 'sortBy' => 'sortOrder', 'asc' => true]);

        $before = null;
        foreach ($categories['collection'] as $cat) {
            if ($cat->get('id') === $category->get('id')) {
                return $before;
            }
            $before = $cat->get('id');
        }

        return null;
    }
}
