# Quick Start: Fix Invisible Marketplace Skills

**Issue:** Custom marketplace skills not visible in Claude Code
**Root Cause:** GitHub Issue [#10568](https://github.com/anthropics/claude-code/issues/10568) - Hardcoded marketplace filtering
**Status:** Temporary workaround available

---

## The Fix (2 Steps)

### Step 1: Copy this prompt to Claude Code

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

### Step 2: Restart Claude Code

```bash
# Exit Claude Code
# Press Ctrl+D twice

# Start Claude Code
claude -c
```

### Step 3: Verify

Ask Claude: **"What skills are available?"**

You should now see all your custom marketplace skills!

---

## What This Does

- ✅ Automatically finds all your custom marketplaces
- ✅ Discovers all skills in those marketplaces
- ✅ Injects them into Anthropic's marketplace (the only one that works)
- ✅ Makes all skills visible and usable
- ✅ Creates backup for easy revert

## How to Revert

```bash
cp ~/.claude/plugins/marketplaces/anthropic-agent-skills/.claude-plugin/marketplace.json.backup \
   ~/.claude/plugins/marketplaces/anthropic-agent-skills/.claude-plugin/marketplace.json
```

Then restart Claude Code.

---

## More Information

- **Full Analysis:** See `MARKETPLACE_ANALYSIS.md`
- **GitHub Issue:** https://github.com/anthropics/claude-code/issues/10568
- **Status Updates:** https://github.com/anthropics/claude-code/issues/10568#issuecomment-3467411762

---

**Last Updated:** 2025-10-30
**Works With:** Claude Code v2.0.27+
**Valid Until:** Official Anthropic fix released
