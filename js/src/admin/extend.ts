import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';
import ProfileCoverSettingsPage from './components/ProfileCoverSettingsPage';

export default [
  new Extend.Admin()
    .page(ProfileCoverSettingsPage)
    .permission(
      () => ({
        icon: 'fas fa-image',
        label: app.translator.trans('forumaker-profile-cover.admin.permission.set_cover'),
        permission: 'setProfileCover',
      }),
      'start'
    ),
];