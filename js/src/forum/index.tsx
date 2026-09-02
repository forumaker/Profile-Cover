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

  if ('fof-blog' in flarum.extensions) {
    extend('ext:fof/blog/forum/components/BlogItemSidebar/BlogAuthor', 'view', function (view: Mithril.Vnode) {
      const author = this.attrs.loading ? null : this.attrs.article ? this.attrs.article.user() : this.attrs.user;

      if (!author || !author.cover()) return;

      const background = (view.children as Mithril.Vnode[])?.find(
        (child) => typeof child?.attrs?.className === 'string' && child.attrs.className.includes('FoFBlog-Article-Author-background')
      );

      if (!background) {
        // This scans fof/blog's own render tree for a class name it doesn't
        // expose as a stable hook — a fof/blog markup refactor can silently
        // break this integration with no other symptom than a missing
        // background image. Surface it in debug mode instead of failing quiet.
        if (app.forum.attribute('debug')) {
          console.warn('[forumaker-profile-cover] Could not find FoFBlog-Article-Author-background — fof/blog markup may have changed.');
        }

        return;
      }

      const position = author.cover_position() ?? 50;

      background.attrs.style = Object.assign(background.attrs.style || {}, {
        backgroundImage: `url(${author.cover()})`,
        backgroundPosition: `center ${position}%`,
        backgroundSize: 'cover',
      });
    });
  }

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