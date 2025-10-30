# Marketplace Skills Visibility Workaround Applied

**Date:** 2025-10-29
**Issue:** Claude Code GitHub Issue #10568
**Root Cause:** Skill tool only recognizes Anthropic's "skills" array format

---

## What Was Changed

Added a temporary container plugin to Netresearch marketplace to make skills visible:

**File:** `/home/sme/.claude/plugins/marketplaces/netresearch-claude-code-marketplace/.claude-plugin/marketplace.json`

**Backup:** `/home/sme/.claude/plugins/marketplaces/netresearch-claude-code-marketplace/.claude-plugin/marketplace.json.backup`

**Change:** Added plugin entry at line 88-102:
```json
{
  "name": "netresearch-skills-bundle",
  "description": "Temporary container plugin for skill visibility workaround (Claude Code issue #10568)",
  "source": "./",
  "strict": false,
  "skills": [
    "./skills/typo3-docs",
    "./skills/typo3-testing",
    "./skills/typo3-ddev",
    "./skills/typo3-core-contributions",
    "./skills/typo3-conformance",
    "./skills/netresearch-branding",
    "./skills/agents"
  ]
}
```

## What This Does

- ✅ Makes all 7 Netresearch skills visible to Claude
- ✅ Preserves original spec-compliant individual plugin entries
- ✅ Zero data loss - all metadata intact
- ✅ Easy to revert when official fix is released

## Skills Now Available

1. **typo3-docs** - TYPO3 extension documentation
2. **typo3-testing** - TYPO3 testing infrastructure
3. **typo3-ddev** - DDEV environment setup
4. **typo3-core-contributions** - TYPO3 core contribution workflow
5. **typo3-conformance** - TYPO3 coding standards validation
6. **netresearch-branding** - Netresearch brand guidelines
7. **agents** - AGENTS.md file generation

## Next Steps

**To activate the changes:**
```bash
# Restart Claude Code to reload plugins
# Skills should now be visible and usable
```

**To verify it worked:**
Ask Claude: "What skills are available?"
Expected: Should now list Netresearch skills

**To revert when official fix is released:**
```bash
# Restore from backup
cp /home/sme/.claude/plugins/marketplaces/netresearch-claude-code-marketplace/.claude-plugin/marketplace.json.backup \
   /home/sme/.claude/plugins/marketplaces/netresearch-claude-code-marketplace/.claude-plugin/marketplace.json

# Or manually remove the "netresearch-skills-bundle" entry
# Keep all 7 original individual plugin entries
```

## Related Documentation

- **Root Cause Analysis:** `MARKETPLACE_ANALYSIS.md`
- **Bug Report:** GitHub Issue #10568
- **Official Fix Status:** Monitor https://github.com/anthropics/claude-code/issues/10568

---

**Status:** ⏳ Temporary workaround active - waiting for official Anthropic fix
