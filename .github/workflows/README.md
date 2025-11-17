# GitHub Actions Workflows

## CI Workflow (`ci.yml`)

Runs on every push and pull request to `main` and `develop` branches.

**Jobs:**
- Code quality checks (PHPStan Level 10, PHP-CS-Fixer)
- Unit tests (PHP 8.1, 8.2, 8.3)
- Functional tests (TYPO3 12.4, 13.4)

## TER Publishing Workflow (`publish-to-ter.yml`)

Automatically publishes extension versions to the TYPO3 Extension Repository (TER) when a GitHub release is created.

### Setup Requirements

**Required GitHub Secrets** (Settings → Secrets and variables → Actions):

1. **`TYPO3_EXTENSION_KEY`**
   - Value: `nr_temporal_cache`
   - The official TYPO3 extension key

2. **`TYPO3_TER_ACCESS_TOKEN`**
   - Value: Your personal TER API token
   - Get it from: https://extensions.typo3.org/my-extensions/
   - Click on your profile → "API Access Token"

### Usage

1. **Prepare Release**
   - Ensure `ext_emconf.php` version matches release version
   - Update `CHANGELOG.md` with release notes
   - All tests must pass on `main` branch

2. **Create Release**
   ```bash
   git tag -a v1.0.0 -m "Release version 1.0.0

   - First stable release
   - Addresses TYPO3 Forge Issue #14277
   - Full documentation at https://docs.typo3.org/"

   git push origin v1.0.0
   ```

3. **Publish GitHub Release**
   - Go to: https://github.com/netresearch/t3x-nr-temporal-cache/releases
   - Click "Draft a new release"
   - Select the tag (e.g., `v1.0.0`)
   - Add release title and description
   - Click "Publish release"

4. **Automatic TER Publishing**
   - Workflow triggers automatically on release publish
   - Validates semantic versioning (v1.2.3 format required)
   - Extracts release notes from tag annotation
   - Publishes to TER via TYPO3 Tailor
   - Check workflow status at: Actions tab

### Version Format

**Required format**: `vMAJOR.MINOR.PATCH` (e.g., `v1.0.0`, `v0.9.5`)

- Must start with `v`
- Use semantic versioning
- No suffixes like `-beta`, `-rc1` (not supported by TER workflow)

### Troubleshooting

**Workflow fails: "Tag format invalid"**
- Ensure tag matches pattern: `v[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}`
- Examples: ✅ `v1.0.0`, ❌ `1.0.0`, ❌ `v1.0.0-beta`

**Workflow fails: "TYPO3_EXTENSION_KEY not set"**
- Add the secret in repository settings
- Value must be: `nr_temporal_cache`

**Workflow fails: "Authentication failed"**
- Regenerate TER API token at https://extensions.typo3.org/
- Update `TYPO3_TER_ACCESS_TOKEN` secret

**Workflow succeeds but extension not visible on TER**
- TER may take a few minutes to update
- Check https://extensions.typo3.org/extension/nr_temporal_cache/
- Verify upload at https://extensions.typo3.org/my-extensions/

### Manual TER Publishing

If automated publishing fails, you can publish manually using Tailor:

```bash
composer global require typo3/tailor

# Authenticate
tailor ter:login

# Publish version
tailor ter:publish --comment "Release notes here" 1.0.0
```

### References

- [TYPO3 Extension Repository](https://extensions.typo3.org/)
- [TYPO3 Tailor Documentation](https://github.com/TYPO3/tailor)
- [GitHub Actions Documentation](https://docs.github.com/en/actions)
