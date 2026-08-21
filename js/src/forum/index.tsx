import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import UserCard from 'flarum/forum/components/UserCard';
import UserPage from 'flarum/forum/components/UserPage';
import UserControls from 'flarum/forum/utils/UserControls';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';
import CoverEditorModal from './components/CoverEditorModal';

export { default as extend } from './extend';

app.initializers.add('forumaker-profile-cover', () => {
  extend(UserCard.prototype, 'view', function (view: Mithril.Vnode) {
    if (!this.attrs.user.cover()) return;

    const coverUrl     = this.attrs.user.cover();
    const thumbnailUrl = this.attrs.user.cover_thumbnail();

    if (!coverUrl) return;

    const isProfilePage = app.current.matches(UserPage);
    const imageUrl      = (!isProfilePage && thumbnailUrl) ? thumbnailUrl : coverUrl;
    const position      = this.attrs.user.cover_position() ?? 50;

    view.attrs.style = Object.assign(view.attrs.style || {}, {
      '--background-image': `url(${imageUrl})`,
      '--background-position': `center ${position}%`,
    });
  });

  extend(UserControls, 'moderationControls', function (items, user) {
    if (!user.canSetProfileCover()) return;

    items.add(
      'cover',
      <Button icon="fas fa-image" onclick={() => app.modal.show(CoverEditorModal, { user })}>
        {app.translator.trans('forumaker-profile-cover.forum.cover')}
      </Button>
    );

    return items;
  });
});