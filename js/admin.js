import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';

import { CLIENT_ID_SETTING, CLIENT_SECRET_SETTING, TENANT_SETTING } from './src/common/constants';

function trans(key, params) {
  const id = `tacraft-sociality-microsoft-oauth.admin.settings.${key}`;

  return params === undefined ? app.translator.trans(id) : app.translator.trans(id, params);
}

function callbackUrl() {
  const baseUrl = String(app.forum.attribute('baseUrl') ?? '').replace(/\/+$/, '');

  return `${baseUrl}/microsoft-oauth/callback`;
}

export const extend = [
  new Extend.Admin()
    .setting(() => ({
      setting: CLIENT_ID_SETTING,
      type: 'text',
      label: trans('client_id_label'),
      help: trans('callback_help', { url: callbackUrl() }),
      placeholder: '00000000-0000-0000-0000-000000000000',
    }))
    .setting(() => ({
      setting: CLIENT_SECRET_SETTING,
      type: 'text',
      label: trans('client_secret_label'),
    }))
    .setting(() => ({
      setting: TENANT_SETTING,
      type: 'text',
      label: trans('tenant_label'),
      help: trans('tenant_help'),
      placeholder: 'common',
    })),
];
