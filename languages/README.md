# VHL Europe WooCommerce Translations

This directory contains translation files for the VHL Europe WooCommerce plugin.

## Available Languages

- English (default)
- Slovak (sk_SK) ✅ MO file available
- Czech (cs_CZ) ✅ MO file available  
- German (de_DE) ✅ MO file available
- Hungarian (hu_HU) ✅ MO file available
- Ukrainian (uk) ✅ MO file available
- Polish (pl_PL) ✅ MO file available
- Italian (it_IT) ✅ MO file available
- French (fr_FR) ✅ MO file available
- Spanish (es_ES) ✅ MO file available
- Romanian (ro_RO) ✅ MO file available
- Bulgarian (bg_BG) ✅ MO file available
- Dutch (nl_NL) ✅ MO file available
- Slovenian (sl_SI) ✅ MO file available
- Croatian (hr_HR) ✅ MO file available

## Files Structure

- `*.pot` - Template file for translations
- `*.po` - Translation source files (human-readable)
- `*.mo` - Compiled translation files (required by WordPress)

## Generating MO Files

### Option 1: Using msgfmt (recommended)
```bash
msgfmt vhl-europe-woocommerce-sk_SK.po -o vhl-europe-woocommerce-sk_SK.mo
```

### Option 2: Using Poedit
1. Open the .po file in Poedit
2. Save the file (automatically generates .mo file)

### Option 3: Using WordPress.org translation tools
Upload the .po files to WordPress.org for community translation.

## Translation Status

Currently implemented basic translations for:
- Admin interface
- Settings pages
- Error messages
- Log interface
- Form labels and descriptions

## Contributing

To contribute translations:

1. Download the .pot template file
2. Create/edit the .po file for your language
3. Compile to .mo format
4. Test in WordPress admin
5. Submit via GitHub or email

## WordPress Best Practices

The plugin follows WordPress i18n best practices:
- Text domain: `vhl-europe-woocommerce`
- Domain path: `/languages`
- Proper escaping and sanitization
- Context-aware translations
- Plural forms support

## Testing Translations

1. Set your WordPress language in Settings > General
2. Ensure the .mo file exists for your language
3. Check admin pages for translated strings
4. Verify log interface translations