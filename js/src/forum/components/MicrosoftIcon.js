/**
 * The four-square Microsoft logo as a vnode.
 *
 * This is a plain helper rather than a component: Flarum 2 runs on Mithril 2,
 * where a component function must return a `{ view }` closure.
 */
export default function microsoftLogo(className = 'MicrosoftOAuth-icon') {
  return (
    <svg className={className} viewBox="0 0 23 23" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
      <rect x="1" y="1" width="10" height="10" fill="#f25022" />
      <rect x="12" y="1" width="10" height="10" fill="#7fba00" />
      <rect x="1" y="12" width="10" height="10" fill="#00a4ef" />
      <rect x="12" y="12" width="10" height="10" fill="#ffb900" />
    </svg>
  );
}
