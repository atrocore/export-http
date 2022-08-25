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
use Espo\ORM\EntityCollection;
use ExportHttp\TwigFilter\Shopware6Uuid;

class Shopware6CreateCategoryId extends AbstractTwigFunction
{
    public function __construct()
    {
        $this->addDependency('serviceFactory');
        $this->addDependency('entityManager');
        $this->addDependency(Shopware6Uuid::class);
    }

    public function run(string $categoryId, string $channelId, string $cmsPageId): ?string
    {
        if (empty($categoryId)) {
            return null;
        }

        $category = $this->getInjection('serviceFactory')->create('Category')->getEntity($categoryId);
        if (empty($category)) {
            return null;
        }

        $categoryRouteCollection = new EntityCollection();
        $categoryRoot = $this->getCategoryRoot($category, $categoryRouteCollection);

        $channel = $this->getInjection('serviceFactory')->create('Channel')->getEntity($channelId);
        if (empty($channel)) {
            return null;
        }

        $rootsIds = array_column($channel->get('categories')->toArray(), 'id');

        if (!in_array($categoryRoot->get('id'), $rootsIds)) {
            return null;
        }

        foreach (array_reverse($categoryRouteCollection->toArray()) as $record) {
            $this->createCategory($record, $cmsPageId);
        }

        return $this->getInjection(Shopware6Uuid::class)->filter($categoryId);
    }

    protected function getCategoryRoot(Entity $category, EntityCollection $collection): Entity
    {
        $collection->append($category);
        if (empty($parent = $category->get('categoryParent'))) {
            return $category;
        }

        return $this->getCategoryRoot($parent, $collection);
    }

    protected function createCategory(array $category, string $cmsPageId): void
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
                'id'        => $this->getInjection(Shopware6Uuid::class)->filter($category['id']),
                'name'      => $category['name'],
                'visible'   => true,
                'parentId'  => empty($category['categoryParentId']) ? null : $this->getInjection(Shopware6Uuid::class)->filter($category['categoryParentId'])
            ])
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_exec($ch);
        curl_close($ch);
    }
}
