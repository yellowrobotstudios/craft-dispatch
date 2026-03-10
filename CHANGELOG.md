# Changelog

## 1.0.0 - 2026-03-09

### Added
- Email template management via the control panel
- Event-driven email sending with config-based hooks
- Fluent hook builder API (Scout-style)
- Twig rendering for subject, HTML body, and plain text body
- Layout template wrapping (per-hook or global default)
- CC/BCC support via resolver closures
- List and individual send modes
- Multi-channel support: email, Slack, and webhooks
- Live preview with element picker and transformer-based variables
- Preview element filtering via criteria
- Config-backed templates protected from deletion
- Email send logging with CP viewer
- Automatic draft/revision/project-config guards on element events
- Queue-based sending with element rehydration
