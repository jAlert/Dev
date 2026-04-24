# Tech Stack

| Layer                 | Technology                                                       |
|-------                |------------                                                      |
| Backend Framework     | Laravel (PHP 8.4)                                                |
| Interactive UI        | Livewire 3 + Volt                                                |
| CSS                   | Tailwind CSS 3                                                   |
| Build Tool            | Vite                                                             |
| Auth                  | Laravel Sanctum (API) + Google OAuth (Socialite) + TOTP 2FA      |
| Permissions           | Spatie Laravel Permission 7.2                                    |
| Database              | MySQL                                                            |
| Queue                 | Database-backed                                                  |
| Testing               | Pest PHP                                                         |
| Theming               | CSS Custom Properties (6 built-in color themes, user-selectable) |

## Color Themes

Users can select a sidebar/accent color theme from their profile page. The selected theme is saved to `users.theme` and applied at page load via CSS custom properties injected into `<head>`.

| Theme | Sidebar | Accent |
|---|---|---|
| Indigo (default) | `#1e1e2f` | `#6366f1` |
| Blue | `#0d1b2e` | `#3b82f6` |
| Green | `#0f1f18` | `#22c55e` |
| Rose | `#2a0f16` | `#f43f5e` |
| Amber | `#1f1a0d` | `#f59e0b` |
| Slate | `#1a1f2e` | `#64748b` |

CSS variables used: `--sidebar-bg`, `--sidebar-header-bg`, `--sidebar-active-bg`, `--accent`. Switching themes updates these variables live via a Livewire event + JavaScript listener — no page reload required.

### Theme CSS Classes (Navigation)

The sidebar navigation uses semantic CSS classes bound to theme variables, rather than hardcoded colors:

| Class | Applied to | Variable |
|---|---|---|
| `prms-nav` | `<nav>` root | `--sidebar-bg` |
| `prms-nav-header` | Logo/header bar | `--sidebar-header-bg` |
| `prms-nav-divider` | Section dividers | `--sidebar-active-bg` |
| `prms-nav-item` | Nav links | Hover/active uses `--sidebar-active-bg` |
| `prms-nav-accent` | Icons | `--accent` |
| `prms-nav-badge` | Approval queue badge | `--accent` |
