import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Switch from 'flarum/common/components/Switch';
import Button from 'flarum/common/components/Button';
import withAttr from 'flarum/common/utils/withAttr';
import formatBytes from '../../common/formatBytes';
import type m from 'mithril';

function Section(iconClass: string, title: string, ...children: m.Children[]) {
  return (
    <section className="ProfileCover-Section">
      <h3>
        <i className={iconClass} aria-hidden="true" />
        {title}
      </h3>
      <div className="ProfileCover-Section-content">{children}</div>
    </section>
  );
}

export default class ProfileCoverSettingsPage extends ExtensionPage {
  loadingRecreate = false;
  loadingDelete = false;

  content() {
    const maxSize    = this.setting('forumaker-profile-cover.max_size', '2048');
    const thumbWidth = this.setting('forumaker-profile-cover.thumbnail_width', '500');

    return (
      <div className="ProfileCoverPage">
        <div className="ProfileCoverPage-content">

          {Section(
            'fas fa-images',
            app.translator.trans('forumaker-profile-cover.admin.sections.thumbnails') as string,

            <div className="Form-group">
              {Switch.component(
                {
                  state: this.setting('forumaker-profile-cover.thumbnails', '0')() === '1',
                  onchange: (val: boolean) =>
                    this.setting('forumaker-profile-cover.thumbnails')(val ? '1' : '0'),
                },
                app.translator.trans('forumaker-profile-cover.admin.thumbnails')
              )}
              <p className="helpText">
                {app.translator.trans('forumaker-profile-cover.admin.thumbnails_help')}
              </p>
            </div>,

            <div className="Form-group">
              <label>{app.translator.trans('forumaker-profile-cover.admin.thumbnail_width')}</label>
              <input
                type="number"
                className="FormControl"
                value={thumbWidth()}
                oninput={withAttr('value', thumbWidth)}
                min="100"
                max="2000"
              />
              <p className="helpText">
                {app.translator.trans('forumaker-profile-cover.admin.thumbnail_width_help')}
              </p>
            </div>,

            <div className="Form-group ProfileCover-actions">
              <Button
                className="Button"
                icon="fas fa-rotate"
                loading={this.loadingRecreate}
                disabled={this.loadingDelete}
                onclick={() => this.doAction('recreate')}
              >
                {app.translator.trans('forumaker-profile-cover.admin.recreate_thumbnails')}
              </Button>
              <Button
                className="Button Button--danger"
                icon="fas fa-trash"
                loading={this.loadingDelete}
                disabled={this.loadingRecreate}
                onclick={() => this.doAction('delete')}
              >
                {app.translator.trans('forumaker-profile-cover.admin.delete_thumbnails')}
              </Button>
            </div>
          )}

          {Section(
            'fas fa-sliders',
            app.translator.trans('forumaker-profile-cover.admin.sections.general') as string,

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
          )}

          <div className="Form-group">{this.submitButton()}</div>
        </div>
      </div>
    );
  }

  async doAction(action: 'recreate' | 'delete') {
    if (action === 'recreate') {
      this.loadingRecreate = true;
    } else {
      this.loadingDelete = true;
    }
    m.redraw();

    try {
      await app.request<object>({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/forumaker-profile-cover/thumbnails/${action}`,
      });

      app.alerts.show(
        { type: 'success' },
        app.translator.trans(`forumaker-profile-cover.admin.${action}_success`)
      );
    } catch {
      app.alerts.show(
        { type: 'error' },
        app.translator.trans(`forumaker-profile-cover.admin.${action}_error`)
      );
    } finally {
      if (action === 'recreate') {
        this.loadingRecreate = false;
      } else {
        this.loadingDelete = false;
      }
      m.redraw();
    }
  }
}