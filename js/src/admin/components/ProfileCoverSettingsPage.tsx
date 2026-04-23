import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Switch from 'flarum/common/components/Switch';
import withAttr from 'flarum/common/utils/withAttr';
import formatBytes from '../../common/formatBytes';

export default class ProfileCoverSettingsPage extends ExtensionPage {
  content() {
    const maxSize = this.setting('forumaker-profile-cover.max_size', '2048');

    return (
      <div className="ProfileCoverAdmin">
        <div className="container">
          <div className="Form-group">
            {Switch.component(
              {
                state: this.setting('forumaker-profile-cover.thumbnails')() === '1',
                onchange: (val: boolean) =>
                  this.setting('forumaker-profile-cover.thumbnails')(val ? '1' : '0'),
              },
              app.translator.trans('forumaker-profile-cover.admin.thumbnails')
            )}
            <p className="helpText">
              {app.translator.trans('forumaker-profile-cover.admin.thumbnails_help')}
            </p>
          </div>

          <div className="Form-group">
            <label>{app.translator.trans('forumaker-profile-cover.admin.max_size')}</label>
            <div className="ProfileCover-size-input">
              <input
                type="number"
                className="FormControl"
                value={maxSize()}
                oninput={withAttr('value', maxSize)}
              />
              <input
                className="FormControl"
                value={formatBytes(Number(maxSize()) * Math.pow(2, 10))}
                disabled
              />
            </div>
          </div>

          <div className="Form-group">{this.submitButton()}</div>
        </div>
      </div>
    );
  }
}