import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';

const ratio = 21 / 9;
const t = (key: string) => app.translator.trans(`forumaker-profile-cover.forum.${key}`);
const clamp = (value: number, min: number, max: number) => Math.max(min, Math.min(max, value));

const corners = {
  nw: { x: -1, y: -1 },
  ne: { x: 1, y: -1 },
  sw: { x: -1, y: 1 },
  se: { x: 1, y: 1 },
};

type Corner = keyof typeof corners;

export interface CropArea {
  x: number;
  y: number;
  width: number;
  height: number;
}

interface Attrs {
  url: string;
  position: number;
  disabled: boolean;
  onchange: (area: CropArea) => void;
  onerror: () => void;
}

export default class CoverCrop extends Component<Attrs> {
  private imageWidth = 0;
  private imageHeight = 0;
  private x = 0;
  private y = 0;
  private width = 0;
  private drag: { id: number; corner?: Corner; startX: number; startY: number; x: number; y: number; width: number } | null = null;

  private get height(): number {
    return this.width / ratio;
  }

  private load(image: HTMLImageElement): void {
    this.imageWidth = image.naturalWidth;
    this.imageHeight = image.naturalHeight;
    if (!this.imageWidth || !this.imageHeight) return this.attrs.onerror();

    this.width = Math.min(this.imageWidth, this.imageHeight * ratio);
    this.x = (this.imageWidth - this.width) / 2;
    this.y = ((this.imageHeight - this.height) * this.attrs.position) / 100;
    this.report();
  }

  private report(): void {
    const width = Math.min(this.imageWidth, Math.max(1, Math.round(this.width)));
    const height = Math.min(this.imageHeight, Math.max(1, Math.round(this.height)));
    this.attrs.onchange({
      x: clamp(Math.round(this.x), 0, this.imageWidth - width),
      y: clamp(Math.round(this.y), 0, this.imageHeight - height),
      width,
      height,
    });
  }

  private move(x: number, y: number): void {
    this.x = clamp(x, 0, this.imageWidth - this.width);
    this.y = clamp(y, 0, this.imageHeight - this.height);
    this.report();
  }

  private resize(width: number, corner: Corner, origin: { x: number; y: number; width: number }): void {
    const { x, y } = corners[corner];
    const anchorX = origin.x + (x < 0 ? origin.width : 0);
    const anchorY = origin.y + (y < 0 ? origin.width / ratio : 0);
    const limit = Math.min(x < 0 ? anchorX : this.imageWidth - anchorX, (y < 0 ? anchorY : this.imageHeight - anchorY) * ratio);
    const minimum = Math.min(limit, Math.min(this.imageWidth, this.imageHeight * ratio) * 0.2);
    this.width = clamp(width, minimum, limit);
    this.x = x < 0 ? anchorX - this.width : anchorX;
    this.y = y < 0 ? anchorY - this.height : anchorY;
    this.report();
  }

  private begin(event: PointerEvent): void {
    if (this.attrs.disabled || this.drag || event.button !== 0 || !this.imageWidth) return;

    const stage = event.currentTarget as HTMLElement;
    const target = event.target as HTMLElement;
    const corner = target.closest<HTMLElement>('.CoverCrop-resize')?.dataset.corner as Corner | undefined;
    if (!target.closest('.CoverCrop-frame')) {
      const rect = stage.getBoundingClientRect();
      this.move(
        ((event.clientX - rect.left) * this.imageWidth) / rect.width - this.width / 2,
        ((event.clientY - rect.top) * this.imageHeight) / rect.height - this.height / 2
      );
    }
    stage.setPointerCapture(event.pointerId);
    this.drag = {
      id: event.pointerId,
      corner,
      startX: event.clientX,
      startY: event.clientY,
      x: this.x,
      y: this.y,
      width: this.width,
    };
  }

  private update(event: PointerEvent): void {
    const drag = this.drag;
    if (!drag || drag.id !== event.pointerId || this.attrs.disabled) return;

    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const dx = ((event.clientX - drag.startX) * this.imageWidth) / rect.width;
    const dy = ((event.clientY - drag.startY) * this.imageHeight) / rect.height;
    if (drag.corner) {
      const { x, y } = corners[drag.corner];
      this.resize(drag.width + (x * dx + y * dy / ratio) / (1 + 1 / (ratio * ratio)), drag.corner, drag);
    } else {
      this.move(drag.x + dx, drag.y + dy);
    }
  }

  private end(event: PointerEvent): void {
    if (this.drag?.id === event.pointerId) this.drag = null;
  }

  private keyboard(event: KeyboardEvent, corner?: Corner): void {
    if (this.attrs.disabled) return;

    const step = Math.max(1, Math.round(this.imageWidth * (event.shiftKey ? 0.05 : 0.01)));
    const x = event.key === 'ArrowLeft' ? -step : event.key === 'ArrowRight' ? step : 0;
    const y = event.key === 'ArrowUp' ? -step : event.key === 'ArrowDown' ? step : 0;
    if (!x && !y) return;

    event.preventDefault();
    event.stopPropagation();
    if (corner) {
      const direction = corners[corner];
      this.resize(this.width + direction.x * x + direction.y * y, corner, { x: this.x, y: this.y, width: this.width });
    } else {
      this.move(this.x + x, this.y + y);
    }
  }

  view() {
    const percent = (value: number, dimension: number) => `${(value * 100) / dimension}%`;
    return (
      <div className="CoverCrop">
        <div
          className="CoverCrop-stage"
          style={this.imageWidth ? { '--crop-image-ratio': this.imageWidth / this.imageHeight } : undefined}
          onpointerdown={(event: PointerEvent) => this.begin(event)}
          onpointermove={(event: PointerEvent) => this.update(event)}
          onpointerup={(event: PointerEvent) => this.end(event)}
          onpointercancel={(event: PointerEvent) => this.end(event)}
          onlostpointercapture={(event: PointerEvent) => this.end(event)}
        >
          <img
            src={this.attrs.url}
            alt=""
            draggable={false}
            onload={(event: Event) => this.load(event.currentTarget as HTMLImageElement)}
            onerror={this.attrs.onerror}
          />
          {!!this.imageWidth && (
            <div
              className="CoverCrop-frame"
              style={{
                left: percent(this.x, this.imageWidth),
                top: percent(this.y, this.imageHeight),
                width: percent(this.width, this.imageWidth),
                height: percent(this.height, this.imageHeight),
              }}
              role="group"
              aria-label={t('crop_area')}
              tabindex={0}
              onkeydown={(event: KeyboardEvent) => this.keyboard(event)}
            >
              {(Object.keys(corners) as Corner[]).map((corner) => (
                <button
                  key={corner}
                  type="button"
                  className={`CoverCrop-resize CoverCrop-resize--${corner}`}
                  data-corner={corner}
                  aria-label={t(`crop_resize_${corner}`)}
                  disabled={this.attrs.disabled}
                  onkeydown={(event: KeyboardEvent) => this.keyboard(event, corner)}
                />
              ))}
            </div>
          )}
        </div>
      </div>
    );
  }
}
