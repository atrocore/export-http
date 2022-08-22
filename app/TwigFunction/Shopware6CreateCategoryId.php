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

use Espo\ORM\Entity;
use ExportHttp\TwigFilter\Shopware6Uuid;

class Shopware6CreateCategoryId extends AbstractTwigFunction
{
    public function __construct()
    {
        $this->addDependency('serviceFactory');
        $this->addDependency('entityManager');
        $this->addDependency(Shopware6Uuid::class);
    }

    public function run(...$args)
    {
        if (empty($args)) {
            return null;
        }

        $categoryId = $args[0];
        $rootsIds = $args[1];
        $cmsPageId = $args[2];

        $category = $this->getInjection('serviceFactory')->create('Category')->getEntity($categoryId);
        if (empty($category)) {
            return null;
        }

        $categoryRoute = $this->getCategoryRoot($category);

        if (!in_array($categoryRoute->get('id'), $rootsIds)) {
            return null;
        }

        $this->createCategoryTree($categoryRoute, $cmsPageId);

        return $this->getInjection(Shopware6Uuid::class)->filter($categoryId);
    }

    protected function getCategoryRoot(Entity $category): Entity
    {
        if (empty($parent = $category->get('categoryParent'))) {
            return $category;
        }

        return $this->getCategoryRoot($parent);
    }

    protected function createCategoryTree(Entity $category, string $cmsPageId): void
    {
        $apiUrlData = parse_url($this->getFeedData()['httpUrl']);
        $apiHost = $apiUrlData['scheme'] . '://' . $apiUrlData['host'];

        $connectionData = $this->getConnectionData();

        $headers = [
            'Content-Type: application/json',
            "Authorization: {$connectionData['token_type']} {$connectionData['access_token']}"
        ];

        $ch = curl_init("$apiHost/api/category");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt(
            $ch, CURLOPT_POSTFIELDS, json_encode([
                'active'    => true,
                'cmsPageId' => $cmsPageId,
                'id'        => $this->getInjection(Shopware6Uuid::class)->filter($category->get('id')),
                'name'      => $category->get('name'),
                'visible'   => true,
                'parentId'  => empty($category->get('categoryParentId')) ? null : $this->getInjection(Shopware6Uuid::class)->filter($category->get('categoryParentId'))
            ])
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_exec($ch);
        curl_close($ch);

        foreach ($category->getChildren() as $child) {
            $this->createCategoryTree($child, $cmsPageId);
        }
    }
}
