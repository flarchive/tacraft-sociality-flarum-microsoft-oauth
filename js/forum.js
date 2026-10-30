import app from 'flarum/forum/app';
import { extend as extendMethod } from 'flarum/common/extend';
import LogInButtons from 'flarum/forum/components/LogInButtons';

import { AUTH_PATH, ENABLED_ATTRIBUTE } from './src/common/constants';
import MicrosoftLinkedAccount from './src/forum/components/MicrosoftLinkedAccount';
import microsoftLogo from './src/forum/components/MicrosoftIcon';
import { handleAuthRedirect } from './src/forum/handleAuthRedirect';

function logInUrl() {
  const baseUrl = String(app.forum.attribute('baseUrl') ?? '').replace(/\/+$/, '');
  const current = `${window.location.pathname}${window.location.search}`;
  const returnTo = current.startsWith(AUTH_PATH) ? '/' : current;

  return `${baseUrl}${AUTH_PATH}?returnTo=${encodeURIComponent(returnTo)}`;
}

function logInButton() {
  return (
    <a className="Button LogInButton LogInButton--microsoft" href={logInUrl()}>
      {microsoftLogo('Button-icon MicrosoftOAuth-icon')}
      <span className="Button-label">
        <span className="Button-labelText">{app.translator.trans('tacraft-sociality-microsoft-oauth.forum.log_in_with')}</span>
      </span>
    </a>
  );
}

export const extend = [
  {
    extend() {
      extendMethod(LogInButtons.prototype, 'items', function (items) {
        if (!app.forum.attribute(ENABLED_ATTRIBUTE)) {
          return;
        }

        items.add('microsoft-oauth', logInButton());
      });

      // Add the link/unlink panel to the user's own security settings, below
      // the QQ (100) and Blessing Skin (99) sections.
      extendMethod('flarum/forum/components/UserSecurityPage', 'settingsItems', function (items) {
        const user = this.user;

        if (!user || user.id() !== app.session.user?.id() || !app.forum.attribute(ENABLED_ATTRIBUTE)) {
          return;
        }

        items.add('microsoftOAuthLinkedAccount', <MicrosoftLinkedAccount user={user} />, 98);
      });

      app.beforeMount(() => {
        void handleAuthRedirect();
      });
    },
  },
];
