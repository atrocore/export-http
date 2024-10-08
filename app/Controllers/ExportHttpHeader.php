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

namespace ExportHttp\Controllers;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Templates\Controllers\Base;

class ExportHttpHeader extends Base
{
    public function actionList($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function getActionListKanban($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionListLinked($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionMassUpdate($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionMassDelete($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionCreateLink($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionRemoveLink($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionFollow($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionUnfollow($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionMerge($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function postActionGetDuplicateAttributes($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function postActionMassFollow($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function postActionMassUnfollow($params, $data, $request)
    {
        throw new Forbidden();
    }
}
