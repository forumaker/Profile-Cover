import Form from 'flarum/common/components/Form';
import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import ItemList from 'flarum/common/utils/ItemList';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import formatBytes from '../../common/formatBytes';
import type Mithril from 'mithril';

interface ApiPayload {
  data: object;
  included?: object[];
}

export default class CoverEditorModal extends Modal {
  maxSize!: number;
  alertAttrs!: { content: string; type?: string };
  loading!: boolean;
  cover!: string | null;
  context!: string;
  position!: number;
  initialPosition!: number;
  savingPosition!: boolean;
  fileInputRef: HTMLInputElement | null = null;

  oninit(vnode: Mithril.Vnode<this>) {
    super.oninit(vnode);

    this.maxSize = parseFloat(app.forum.attribute('forumaker-profile-cover.max_size') || 2048);

    this.alertAttrs = {
      content: app.translator.trans('forumaker-profile-cover.forum.notice', {
        size: formatBytes(this.maxSize * Math.pow(2, 10)),
      }) as string,
    };

    this.loading = false;
    this.cover = this.attrs.user.cover_thumbnail() || this.attrs.user.cover();
    this.position = this.attrs.user.cover_position() ?? 50;
    this.initialPosition = this.position;
    this.savingPosition = false;
    this.context = '';
  }

  content() {
    const attrs: Record<string, any> = {};
    let className = 'Modal-image CoverEditor-cover';

    if (this.cover) {
      attrs.style = { backgroundImage: `url(${this.cover})`, backgroundPosition: `center ${this.position}%` };
      className += ' CoverEditor-active';
    }

    return [
      <input
        type="file"
        accept="image/jpeg,image/png,image/gif,image/bmp,image/webp"
        style="display:none"
        oncreate={(vnode: Mithril.Vnode<this>) => { this.fileInputRef = vnode.dom as HTMLInputElement; }}
        onchange={(e: Event) => {
          const files = (e.target as HTMLInputElement).files;
          if (files?.[0]) this.upload(files[0]);
          (e.target as HTMLInputElement).value = '';
        }}
      />,

      <div className={className} {...attrs}>
        {this.loading ? <LoadingIndicator /> : ''}
      </div>,

      <div className="Modal-body">
        <Form className="Form--centered">{this.fieldsItems().toArray()}</Form>
      </div>,
    ];
  }

  className() {
    return 'Cover-modal Modal--small';
  }

  title() {
    return app.translator.trans('forumaker-profile-cover.forum.edit_cover');
  }

  fieldsItems() {
    const items = new ItemList();

    if (this.cover) {
      items.add('position', this.positionSlider(), 10);
    }

    items.add('actions', this.controlItems().toArray());

    return items;
  }

  positionSlider() {
    const changed = this.position !== this.initialPosition;

    return (
      <div className="Form-group">
        <label>{app.translator.trans('forumaker-profile-cover.forum.position_label')}</label>
        <input
          type="range"
          className="CoverEditor-positionSlider"
          min="0"
          max="100"
          value={this.position}
          oninput={(e: InputEvent) => {
            this.position = parseInt((e.target as HTMLInputElement).value, 10);
          }}
        />
        {changed && (
          <Button
            icon="fas fa-check"
            className="Button Button--block CoverEditor-savePosition"
            loading={this.savingPosition}
            onclick={this.savePosition.bind(this)}
          >
            {app.translator.trans('forumaker-profile-cover.forum.save_position_button')}
          </Button>
        )}
      </div>
    );
  }

  controlItems() {
    const items = new ItemList();

    items.add(
      'upload',
      <Button icon="fas fa-upload" className="Button Button--block Button--primary" onclick={this.openPicker.bind(this)}>
        {app.translator.trans('core.forum.user.avatar_upload_button')}
      </Button>
    );

    items.add(
      'remove',
      <Button icon="fas fa-times" className="Button Button--block" onclick={this.remove.bind(this)}>
        {app.translator.trans('core.forum.user.avatar_remove_button')}
      </Button>
    );

    return items;
  }

  openPicker() {
    this.fileInputRef?.click();
  }

  upload(file: File) {
    if (this.loading) return;

    const data = new FormData();
    data.append('cover', file);

    this.loading = true;
    this.context = 'added';
    this.position = 50;
    m.redraw();

    app
      .request({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/users/${this.attrs.user.id()}/cover`,
        serialize: (raw: any) => raw,
        body: data,
      })
      .then((response: ApiPayload) => {
        this.success(response);
        this.initialPosition = 50;
        this.attrs.user.save({ cover_position: 50 });
      }, this.failure.bind(this));
  }

  savePosition() {
    if (this.savingPosition) return;

    this.savingPosition = true;
    m.redraw();

    this.attrs.user.save({ cover_position: this.position }).then(
      () => {
        this.initialPosition = this.position;
        this.savingPosition = false;
        m.redraw();
      },
      () => {
        this.savingPosition = false;
        m.redraw();
      }
    );
  }

  remove() {
    this.loading = true;
    this.context = 'removed';
    m.redraw();

    app
      .request({
        method: 'DELETE',
        url: `${app.forum.attribute('apiUrl')}/users/${this.attrs.user.id()}/cover`,
      })
      .then(this.success.bind(this), this.failure.bind(this));
  }

  success(response: ApiPayload) {
    app.store.pushPayload(response);
    this.showAlert('success');
    this.loading = false;
    m.redraw();
    this.hide();
  }

  failure() {
    this.showAlert('error');
    this.loading = false;
    m.redraw();
  }

  showAlert(type: string) {
    this.alertAttrs.content = app.translator.trans(`forumaker-profile-cover.forum.${this.context}.${type}`) as string;
    this.alertAttrs.type = type;
  }
}
