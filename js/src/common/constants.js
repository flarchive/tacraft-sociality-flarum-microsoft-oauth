export const EXTENSION_ID = 'tacraft-sociality-microsoft-oauth';

export const SETTINGS_PREFIX = `${EXTENSION_ID}.`;

export const CLIENT_ID_SETTING = `${SETTINGS_PREFIX}client_id`;

export const CLIENT_SECRET_SETTING = `${SETTINGS_PREFIX}client_secret`;

export const TENANT_SETTING = `${SETTINGS_PREFIX}tenant`;

export const AUTH_PATH = '/microsoft-oauth/callback';

export const ENABLED_ATTRIBUTE = 'microsoftOAuthEnabled';

export const REGISTRATION_TOKEN_ENDPOINT = '/registration-token';

export const LINK_STATUS_ENDPOINT = '/microsoft-oauth/link';

export const AUTH_ERROR_PARAM = '_flarum_microsoft_error';

export const AUTH_TOKEN_PARAM = '_flarum_auth';

export const AUTH_MARKER_PARAM = '_flarum_microsoft';

export const AUTH_LINKED_PARAM = '_flarum_microsoft_linked';
