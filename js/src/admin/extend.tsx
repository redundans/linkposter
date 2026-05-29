import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';
import LinkposterSettingsPage from './components/LinkposterSettingsPage';

export default [
  new Extend.Admin() //
    .page(LinkposterSettingsPage),
];
