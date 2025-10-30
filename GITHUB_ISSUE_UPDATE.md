# GitHub Issue Update: Workarounds Tested

## Executive Summary

**Critical Finding:** The Skill tool is not just missing format support - it's **hardcoded to only parse the `anthropic-agent-skills` marketplace**. Even using Anthropic's exact format in a third-party marketplace doesn't work.

---

## Workaround 1: Container Plugin in Netresearch Marketplace ❌ FAILED

### What We Tried

Added a container plugin with "skills" array to Netresearch's marketplace.json, mimicking Anthropic's exact format:

```json
{
  "name": "netresearch-skills-bundle",
  "source": "./",
  "strict": false,
  "skills": [
    "./skills/typo3-ddev",
    "./skills/typo3-docs",
    ...
  ]
}
```

### Steps Taken

1. Modified `/home/sme/.claude/plugins/marketplaces/netresearch-claude-code-marketplace/.claude-plugin/marketplace.json`
2. Added container plugin entry at end of plugins array
3. Ran `claude plugin install netresearch-skills-bundle@netresearch-claude-code-marketplace`
4. Restarted Claude Code
5. Checked if skills appeared

### Result

**FAILED** - Skills still invisible even after:
- ✅ Container plugin properly formatted
- ✅ Container plugin registered in installed_plugins.json
- ✅ Restart completed
- ❌ Skills never appeared in available skills list

### Conclusion

Using Anthropic's format in a third-party marketplace **does not work**. This proves the issue is not just format support but marketplace-specific filtering.

---

## Workaround 2: Inject Skills into Anthropic Marketplace ✅ WORKS

### What We Tried

Added cross-marketplace references to Netresearch skills directly in Anthropic's skills array:

```json
{
  "plugins": [
    {
      "name": "example-skills",
      "skills": [
        "./skill-creator",
        "./mcp-builder",
        ...existing Anthropic skills...
        "../netresearch-claude-code-marketplace/skills/typo3-ddev",
        "../netresearch-claude-code-marketplace/skills/typo3-docs",
        "../netresearch-claude-code-marketplace/skills/typo3-testing",
        "../netresearch-claude-code-marketplace/skills/typo3-core-contributions",
        "../netresearch-claude-code-marketplace/skills/typo3-conformance",
        "../netresearch-claude-code-marketplace/skills/netresearch-branding",
        "../netresearch-claude-code-marketplace/skills/agents"
      ]
    }
  ]
}
```

### Steps Taken

1. Backed up Anthropic's marketplace.json
2. Edited `/home/sme/.claude/plugins/marketplaces/anthropic-agent-skills/.claude-plugin/marketplace.json`
3. Added 7 Netresearch skill paths to "example-skills" skills array
4. Restarted Claude Code
5. Verified skills appeared

### Result

**SUCCESS** ✅ - All 7 Netresearch skills now visible and usable!

### Critical Proof

This proves the Skill tool is **hardcoded to only parse `anthropic-agent-skills` marketplace**. The exact same "skills" array format works when in Anthropic's marketplace but not in Netresearch's marketplace.

---

## How to Apply the Working Workaround

### Prerequisites

- Claude Code installed
- Netresearch marketplace installed
- Access to filesystem where Claude plugins are stored (typically `~/.claude/plugins/`)

### Step 1: Ask Claude Code to Apply the Hack

**Copy and paste this exact prompt into Claude Code:**

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

After Claude applies the changes:

```bash
# Exit Claude Code
# Press Ctrl+D twice

# Restart Claude Code
claude -c
```

### Step 3: Verify It Worked

Ask Claude: "What skills are available?"

You should now see Netresearch skills listed alongside Anthropic skills.

---

## How to Revert

### When Anthropic Fixes the Bug

```bash
# Restore from backup
cp ~/.claude/plugins/marketplaces/anthropic-agent-skills/.claude-plugin/marketplace.json.backup \
   ~/.claude/plugins/marketplaces/anthropic-agent-skills/.claude-plugin/marketplace.json

# Restart Claude Code
```

### If Anthropic Updates Their Marketplace

The hack will be overwritten. You'll need to:
1. Note which Netresearch skills you want
2. Reapply the hack after the update
3. Or wait for official fix

---

## Alternative: Manual Edit

If you prefer to edit manually:

1. **Backup:**
   ```bash
   cp ~/.claude/plugins/marketplaces/anthropic-agent-skills/.claude-plugin/marketplace.json \
      ~/.claude/plugins/marketplaces/anthropic-agent-skills/.claude-plugin/marketplace.json.backup
   ```

2. **Edit the file:**
   ```bash
   nano ~/.claude/plugins/marketplaces/anthropic-agent-skills/.claude-plugin/marketplace.json
   ```

3. **Find the "example-skills" plugin's "skills" array** (around line 29-40)

4. **Add these lines** after the last existing skill (after "./brand-guidelines"):
   ```json
   ,
   "../netresearch-claude-code-marketplace/skills/typo3-ddev",
   "../netresearch-claude-code-marketplace/skills/typo3-docs",
   "../netresearch-claude-code-marketplace/skills/typo3-testing",
   "../netresearch-claude-code-marketplace/skills/typo3-core-contributions",
   "../netresearch-claude-code-marketplace/skills/typo3-conformance",
   "../netresearch-claude-code-marketplace/skills/netresearch-branding",
   "../netresearch-claude-code-marketplace/skills/agents"
   ```

5. **Save and verify JSON:**
   ```bash
   jq empty ~/.claude/plugins/marketplaces/anthropic-agent-skills/.claude-plugin/marketplace.json
   ```

6. **Restart Claude Code**

---

## For Other Custom Marketplaces

This workaround works for ANY custom marketplace. Just replace the paths:

```json
"../your-marketplace-name/skills/your-skill-name"
```

The key insight: **You must inject skills into Anthropic's marketplace** because that's the only one the Skill tool parses.

---

## Impact on Bug Priority

This finding **increases the severity** of the bug:

- **Before:** Missing format support for individual skill plugins
- **After:** Hardcoded marketplace name filtering - complete ecosystem lockout

**Recommendation:** This should be P0/Critical priority as it completely blocks third-party marketplace ecosystem development.

---

## Testing Results

**Environment:**
- Claude Code v2.0.27
- Linux WSL2
- Netresearch Claude Code Marketplace (7 skills)
- Netresearch AI Marketplace (1 skill)

**Workaround 1 (Container in Netresearch):** ❌ Failed
**Workaround 2 (Inject into Anthropic):** ✅ Success

All 7 Netresearch skills now visible and usable after applying Workaround 2.
