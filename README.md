# Log In with Microsoft

Flarum 2 的 Microsoft OAuth 登录扩展，支持个人 Microsoft 账户，以及 Microsoft Entra ID 工作或学校账户。

回调地址固定为 `/microsoft-oauth/callback`，不使用 `/auth/*` 前缀，因此不会和 `/auth/qq` 等已有路由冲突。

## 环境要求

- Flarum 2.0.2 及以上
- PHP 8.3 及以上

本版本不再依赖 FoF OAuth。如果之前只是为了旧版 Microsoft 插件安装 `fof/oauth`，请先禁用并移除它。

旧版通过 FoF OAuth 建立的 Microsoft 绑定使用相同的 provider 名称和用户标识，升级后仍可继续登录。

## 安装

```sh
composer require tacraft-sociality/flarum-microsoft-oauth
php flarum cache:clear
```

## Microsoft Entra ID 配置

1. 打开 [Azure Portal](https://portal.azure.com)，进入 **Microsoft Entra ID → 应用注册 → 新注册**。
2. 选择支持的账户类型。
3. 添加 **Web** 重定向 URI：

   ```text
   https://www.tcscraft.com/microsoft-oauth/callback
   ```

4. 复制 **应用程序（客户端）ID**。
5. 在 **证书和密码** 中创建客户端密码并复制值。
6. 在 Flarum 后台进入 **扩展 → Log In with Microsoft**，填写客户端 ID、客户端密码和可选租户。

### 租户

| 值 | 允许的账户 |
| --- | --- |
| 留空或 `common` | 个人账户和组织账户 |
| `organizations` | 仅组织账户 |
| `consumers` | 仅个人账户 |
| 租户 ID 或域名 | 仅该租户的账户 |

指定具体租户，或 Microsoft 明确标记邮箱已验证时，邮箱会被视为可信；否则注册时只作为建议邮箱预填。

## 账号绑定

登录后进入 **个人资料 → 安全设置**，页面里会多出一个「Microsoft 账号」区域。安装了 QQ、Blessing Skin 登录扩展时，它排在它们下面。

- **未绑定**：显示「未绑定」和「绑定 Microsoft 账号」按钮，跳转 Microsoft 授权后回到安全页。
- **已绑定**：显示脱敏后的账号标识与绑定时间，可「更换绑定的账号」或「解除绑定」。
- 一个 Microsoft 账号只能绑定一个论坛账号；绑定到别人已用的账号会提示失败。
- 换绑会替换当前账号原来的 Microsoft 绑定。
- 解绑只移除这条绑定，不会让当前登录失效；如果邮箱仍能匹配，下次用 Microsoft 登录时可能会自动重新关联。
- 回调地址仍然是 `https://你的域名/microsoft-oauth/callback`，不需要修改。

## 许可证

MIT
