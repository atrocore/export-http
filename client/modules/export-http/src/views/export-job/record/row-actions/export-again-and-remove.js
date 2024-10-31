/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export-http:views/export-job/record/row-actions/export-again-and-remove', 'export:views/export-job/record/row-actions/export-again-and-remove', Dep => {

    return Dep.extend({

        getActionList() {
            let list = Dep.prototype.getActionList.call(this) || [];

            if (['Failed', 'Canceled'].includes(this.model.get('state')) && this.model.get('fileId') && this.model.get('requestUrl') && this.options.acl.edit) {
                list.unshift({
                    action: 'trySendRequestAgain', label: 'trySendRequestAgain', data: {
                        id: this.model.id
                    }
                });
            }

            return list;
        }
    });
});