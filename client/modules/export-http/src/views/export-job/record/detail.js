/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export-http:views/export-job/record/detail', 'export:views/export-job/record/detail', function (Dep) {

    return Dep.extend({

        duplicateAction: false,

        setupActionItems: function () {

            Dep.prototype.setupActionItems.call(this);

            if (['Failed', 'Canceled'].includes(this.model.get('state')) && this.model.get('requestUrl') && this.model.get('fileId')) {
                this.dropdownItemList.push({
                    name: 'trySendRequestAgain',
                    action: 'trySendRequestAgain',
                    label: 'trySendRequestAgain',
                });
            }
        },
        actionTrySendRequestAgain(data) {
            this.notify('Saving...');
            this.model.set('state', 'Pending');
            this.model.set('shouldResend', true);
            this.model.save().then(() => {
                this.notify('Saved', 'success');
            });
        }
    });

});