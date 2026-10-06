# folio-flipbook — یادآور پروژه (Claude Code)

<!-- CRG-REMINDER -->
## 🔎 code-review-graph — اول این، برای صرفه‌جویی توکن

این پروژه در code-review-graph ایندکس شده (alias: `folio-flipbook`). گراف دانش‌کد (نود/یال وابستگی‌ها) از قبل ساخته شده.

**قانون:** قبل از جستجوی گسترده با Read/Grep روی چندین فایل، **اول گراف را پرس‌وجو کن** — توکن بسیار کمتری مصرف می‌شود:

- اگر MCP این ابزار وصل است → با ابزارهای آن محل تابع/کلاس/هوک و وابستگی‌هایش را پیدا کن، نه با باز کردن کل فایل‌ها.
- تأثیر یک تغییر (چه چیزهایی به آن وصل‌اند): `code-review-graph detect-changes --repo .`
- آمار/وضعیت گراف: `code-review-graph status --repo .`

**بعد از تغییر کد** گراف را تازه کن (incremental، سریع):
```
code-review-graph update --repo .
```
فقط وقتی ساختار خیلی عوض شد: `code-review-graph build --repo .`
