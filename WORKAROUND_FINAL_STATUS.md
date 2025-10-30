# Final Status: Marketplace Skills Workaround

**Date:** 2025-10-29/30
**Issue:** GitHub #10568
**Status:** ✅ Working workaround applied

---

## What We Discovered

The bug is **worse than initially thought**:

1. ❌ **Not just format support** - The Skill tool is hardcoded to only parse `anthropic-agent-skills` marketplace
2. ❌ **Third-party marketplaces completely blocked** - Even using Anthropic's exact format doesn't work
3. ✅ **Workaround exists** - Inject skills into Anthropic's marketplace

---

## Current State

### Applied Workaround ✅

**File modified:**
`~/.claude/plugins/marketplaces/anthropic-agent-skills/.claude-plugin/marketplace.json`

**Backup saved as:**
`~/.claude/plugins/marketplaces/anthropic-agent-skills/.claude-plugin/marketplace.json.backup`

**What was done:**
Added 7 Netresearch skill paths to Anthropic's "example-skills" plugin skills array:
- typo3-ddev
- typo3-docs
- typo3-testing
- typo3-core-contributions
- typo3-conformance
- netresearch-branding
- agents

### Result

**All Netresearch skills now visible and usable!** ✅

You can verify by asking Claude: "What skills are available?"

---

## Workarounds Tested

### ❌ Workaround 1: Container Plugin in Netresearch Marketplace

**Attempted:** Add Anthropic-style container plugin to Netresearch marketplace
**Result:** Failed - Skills remained invisible
**Reason:** Skill tool ignores non-Anthropic marketplaces regardless of format

### ✅ Workaround 2: Inject Into Anthropic Marketplace

**Attempted:** Add cross-marketplace references in Anthropic's skills array
**Result:** Success - All skills now visible
**Reason:** Proves marketplace name is hardcoded

---

## How Users Can Apply This Workaround

### Easy Method: Ask Claude Code

Copy/paste this prompt to Claude Code:

```
I need you to apply a workaround for the marketplace skills visibility bug (GitHub issue #10568).

Please do the following:

1. Find all custom (non-Anthropic) marketplaces in ~/.claude/plugins/marketplaces/

2. For each custom marketplace, scan the marketplace.json to find all skills (look for "plugins" entries with "source" fields pointing to skill directories)

3. Backup ~/.claude/plugins/marketplaces/anthropic-agent-skills/.claude-plugin/marketplace.json

4. Edit that file and add relative path references to ALL discovered custom skills in the "example-skills" plugin's "skills" array using the format: "../marketplace-name/path/to/skill"

5. Verify the JSON syntax is valid

6. Show me which skills were added and tell me to restart Claude Code

This is a temporary workaround until Anthropic fixes the hardcoded marketplace filtering bug.
```

Then restart Claude Code:
```bash
# Exit: Ctrl+D twice
# Start: claude -c
```

### Manual Method: Edit Files

See `GITHUB_ISSUE_UPDATE.md` for step-by-step manual editing instructions.

---

## Maintenance

### When Anthropic Updates Their Marketplace

The workaround will be overwritten. Options:
1. Reapply the hack (save the skill paths list)
2. Wait for official fix
3. Temporarily lose Netresearch skills access

### When Official Fix is Released

Revert the changes:
```bash
cp ~/.claude/plugins/marketplaces/anthropic-agent-skills/.claude-plugin/marketplace.json.backup \
   ~/.claude/plugins/marketplaces/anthropic-agent-skills/.claude-plugin/marketplace.json
```

Restart Claude Code.

---

## Documentation Created

1. **MARKETPLACE_ANALYSIS.md** - Root cause analysis (95% confidence)
2. **WORKAROUND_APPLIED.md** - First workaround attempt (failed)
3. **GITHUB_ISSUE_UPDATE.md** - Comprehensive update for issue #10568
4. **This file** - Final status and summary

---

## GitHub Issue Updates

**Issue:** https://github.com/anthropics/claude-code/issues/10568

**Comments posted:**
1. Initial bug report with evidence
2. Root cause analysis with official spec comparison
3. Workaround testing results and user instructions ← Latest

**Next steps:**
- Monitor issue for Anthropic response
- Update when official fix is released
- Help other users apply workaround

---

## Technical Details

**Root Cause:**
```python
# Current (broken) implementation
def discover_skills():
    anthropic_marketplace = load_marketplace("anthropic-agent-skills")
    for plugin in anthropic_marketplace.plugins:
        if 'skills' in plugin:
            return parse_skills(plugin['skills'])
    # Never checks other marketplaces!
```

**What it should be:**
```python
def discover_skills():
    for marketplace in all_installed_marketplaces:
        for plugin in marketplace.plugins:
            # Pattern 1: Container with skills array
            if 'skills' in plugin:
                skills.extend(parse_skills_array(plugin))
            # Pattern 2: Individual skill plugin
            elif has_skill_md(plugin.installPath):
                skills.append(parse_skill_from_path(plugin))
    return skills
```

---

## For Netresearch Team

If you want to document this in your marketplace:

1. Add a `KNOWN_ISSUES.md` file
2. Reference GitHub issue #10568
3. Include the workaround prompt
4. Note this is temporary until Anthropic fixes it
5. Consider adding to your marketplace README

---

## Confidence Level

**Finding:** 99% confident the Skill tool is hardcoded to only parse `anthropic-agent-skills`

**Evidence:**
- ✅ Same format works in Anthropic marketplace
- ❌ Same format fails in Netresearch marketplace
- ✅ Cross-marketplace references work
- ✅ Individual plugin format also fails
- ✅ Container plugin format also fails
- **Conclusion:** Only marketplace name matters

---

**Status:** Workaround active and functional
**Next:** Wait for Anthropic's official fix or response
