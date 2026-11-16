import app from 'flarum/forum/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type User from 'flarum/common/models/User';
import Button from 'flarum/common/components/Button';
import formatBytes from '../../common/formatBytes';
import CoverCrop, { type CropArea } from './CoverCrop';

interface ApiPayload {
  data: object;
  included?: object[];
}

interface CoverEditorModalAttrs extends IInternalModalAttrs {
  user: User;
}

const t = (key: string, params?: Record<string, string>) => app.translator.trans(`forumaker-profile-cover.forum.${key}`, params);

export default class CoverEditorModal extends Modal<CoverEditorModalAttrs> {
  private fileInput: HTMLInputElement | null = null;
  private draft: { file: File; url: string; position: number } | null = null;
  private crop: CropArea | null = null;
  private closed = false;

  private get maxSize(): number {
    return Number(app.forum.attribute('forumaker-profile-cover.max_size') || 2048) * 1024;
  }

  onremove() {
    this.closed = true;
    this.clearDraft();
  }

  private clearDraft(): void {
    if (this.draft) URL.revokeObjectURL(this.draft.url);
    this.draft = null;
    this.crop = null;
  }

  private select(file: File, position = 50): void {
    if (file.size > this.maxSize) {
      this.alertAttrs = { type: 'error', content: t('file_too_large', { size: formatBytes(this.maxSize) }) };
      return;
    }

    this.clearDraft();
    this.alertAttrs = null;
    this.draft = { file, url: URL.createObjectURL(file), position };
  }

  private async editExisting(cover: string): Promise<void> {
    this.loading = true;
    this.alertAttrs = null;
    try {
      const response = await fetch(cover);
      if (!response.ok) throw new Error('Cover could not be loaded');
      const blob = await response.blob();
      const name = new URL(cover, window.location.href).pathname.split('/').pop() || 'cover';
      if (!this.closed) this.select(new File([blob], name, { type: blob.type }), this.attrs.user.cover_position() ?? 50);
    } catch {
      if (!this.closed) this.alertAttrs = { type: 'error', content: t('crop_error') };
    } finally {
      this.loading = false;
      if (!this.closed) m.redraw();
    }
  }

  private async save(method: 'POST' | 'DELETE', data?: FormData): Promise<void> {
    if (this.loading) return;
    this.loading = true;
    this.alertAttrs = null;
    const context = method === 'POST' ? 'added' : 'removed';

    try {
      const response = await app.request<ApiPayload>({
        method,
        url: `${app.forum.attribute('apiUrl')}/users/${this.attrs.user.id()}/cover`,
        ...(data ? { body: data, serialize: (raw: FormData) => raw } : {}),
      });
      app.store.pushPayload(response as any);
      app.alerts.show({ type: 'success' }, t(`${context}.success`));
      if (!this.closed) this.hide();
    } catch {
      if (!this.closed) this.alertAttrs = { type: 'error', content: t(`${context}.error`) };
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  private saveCrop(): void {
    if (!this.draft || !this.crop) return;

    const data = new FormData();
    data.append('cover', this.draft.file);
    data.append('crop', JSON.stringify(this.crop));
    void this.save('POST', data);
  }

  className() {
    return 'Cover-modal Modal--small';
  }

  title() {
    return t('edit_cover');
  }

  content() {
    const cover = this.attrs.user.cover();
    const thumbnail = this.attrs.user.cover_thumbnail() || cover;
    const draft = this.draft;

    return (
      <>
        <input
          type="file"
          accept="image/jpeg,image/png,image/gif,image/bmp,image/webp"
          hidden
          oncreate={(vnode) => { this.fileInput = vnode.dom as HTMLInputElement; }}
          onchange={(event: Event) => {
            const input = event.currentTarget as HTMLInputElement;
            const file = input.files?.[0];
            if (file) this.select(file);
            input.value = '';
          }}
        />
        {!draft && thumbnail && (
          <div className="CoverEditor-cover" style={{
            backgroundImage: `url(${thumbnail})`,
            backgroundPosition: `center ${this.attrs.user.cover_position() ?? 50}%`,
          }} />
        )}
        <div className="Modal-body CoverEditor-body">
          {draft ? (
            <>
              <p className="CoverEditor-help">{t('crop_instructions')}</p>
              <div>
                <CoverCrop
                  key={draft.url}
                  url={draft.url}
                  position={draft.position}
                  disabled={this.loading}
                  onchange={(area) => { this.crop = area; }}
                  onerror={() => {
                    this.clearDraft();
                    this.alertAttrs = { type: 'error', content: t('crop_error') };
                  }}
                />
              </div>
              <div className="CoverEditor-cropActions">
                <Button className="Button Button--primary" disabled={!this.crop} loading={this.loading} onclick={() => this.saveCrop()}>
                  {t('save_cover')}
                </Button>
                <div className="CoverEditor-actions">
                  <Button className="Button" disabled={this.loading} onclick={() => this.clearDraft()}>{t('cancel_crop')}</Button>
                  <Button className="Button" disabled={this.loading} onclick={() => this.fileInput?.click()}>{t('choose_another')}</Button>
                </div>
              </div>
            </>
          ) : (
            <>
              <p className="CoverEditor-help">{t('notice', { size: formatBytes(this.maxSize) })}</p>
              <div className="CoverEditor-actions">
                <Button className="Button Button--primary" icon="fas fa-upload" disabled={this.loading} onclick={() => this.fileInput?.click()}>
                  {t('choose_image')}
                </Button>
                {cover && (
                  <>
                    <Button className="Button" icon="fas fa-crop" loading={this.loading} onclick={() => this.editExisting(cover)}>{t('crop_existing')}</Button>
                    <Button className="Button" icon="fas fa-times" disabled={this.loading} onclick={() => this.save('DELETE')}>
                      {app.translator.trans('core.forum.user.avatar_remove_button')}
                    </Button>
                  </>
                )}
              </div>
            </>
          )}
        </div>
      </>
    );
  }
}
