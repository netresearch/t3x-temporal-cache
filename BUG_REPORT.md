# Bug Report: Marketplace Skills Not Exposed Through Skill Tool

## Summary
Installed marketplace skills are not accessible through the Skill tool, despite being properly registered in `installed_plugins.json`. Only Anthropic's example-skills are exposed.

## Environment
- **Claude Code Version**: 2.0.27
- **Platform**: Linux (WSL2)
- **OS**: Linux 6.6.87.2-microsoft-standard-WSL2
- **Date**: 2025-10-29

## Description
After installing multiple skills from custom marketplaces (netresearch-claude-code-marketplace and netresearch-ai-marketplace), Claude is only aware of skills from the anthropic-agent-skills marketplace. The Skill tool's description only lists:

```
<skill>
<name>example-skills:skill-creator</name>
<location>plugin</location>
</skill>
<skill>
<name>example-skills:mcp-builder</name>
<location>plugin</location>
</skill>
...etc (only Anthropic skills)
```

However, `~/.claude/plugins/installed_plugins.json` shows 9 installed skills:

## Installed Skills (from installed_plugins.json)

### Netresearch Claude Code Marketplace (7 skills)
1. `typo3-docs@netresearch-claude-code-marketplace` - installed 2025-10-27
2. `typo3-testing@netresearch-claude-code-marketplace` - installed 2025-10-27
3. `typo3-conformance@netresearch-claude-code-marketplace` - installed 2025-10-27
4. `netresearch-branding@netresearch-claude-code-marketplace` - installed 2025-10-27
5. `agents@netresearch-claude-code-marketplace` - installed 2025-10-27
6. `typo3-ddev@netresearch-claude-code-marketplace` - installed 2025-10-29
7. `typo3-core-contributions@netresearch-claude-code-marketplace` - installed 2025-10-29

### Netresearch AI Marketplace (1 skill)
8. `typo3-upgrade-estimator@netresearch-ai-marketplace` - installed 2025-10-27

### Anthropic (1 skill)
9. `example-skills@anthropic-agent-skills` - installed 2025-10-27

## Steps to Reproduce
1. Install a custom marketplace (e.g., netresearch-claude-code-marketplace)
2. Install skills from that marketplace
3. Verify skills are in `~/.claude/plugins/installed_plugins.json`
4. Ask Claude to list available skills
5. Observe that only Anthropic example-skills are listed

## Expected Behavior
All installed skills from `installed_plugins.json` should be:
1. Exposed through the Skill tool's available_skills list
2. Invokable by Claude when relevant to the task
3. Discoverable when the user asks "what skills are available?"

## Actual Behavior
- Only skills from `example-skills@anthropic-agent-skills` are exposed
- Custom marketplace skills are invisible to Claude
- Claude has no awareness of installed marketplace skills
- Skills exist on filesystem and are registered, but not accessible

## Impact
- **High**: Users cannot benefit from installed marketplace skills
- Marketplace ecosystem is effectively broken for users
- Skills must be manually discovered through filesystem exploration
- Claude cannot proactively use relevant installed skills

## Evidence

### File: `~/.claude/plugins/installed_plugins.json`
```json
{
  "version": 1,
  "plugins": {
    "typo3-docs@netresearch-claude-code-marketplace": {
      "version": "unknown",
      "installedAt": "2025-10-27T11:27:40.272Z",
      "lastUpdated": "2025-10-27T11:27:40.272Z",
      "installPath": "/home/sme/.claude/plugins/marketplaces/netresearch-claude-code-marketplace/skills/typo3-docs",
      "gitCommitSha": "205dfcb5dc2f46b3a89ab60c8aa5cd51219014d7",
      "isLocal": true
    },
    "typo3-ddev@netresearch-claude-code-marketplace": {
      "version": "unknown",
      "installedAt": "2025-10-29T13:13:08.131Z",
      "lastUpdated": "2025-10-29T13:13:08.131Z",
      "installPath": "/home/sme/.claude/plugins/marketplaces/netresearch-claude-code-marketplace/skills/typo3-ddev",
      "gitCommitSha": "e31cfa2b4e1118d88406722877e7fc2aa722bcf1",
      "isLocal": true
    }
    ... (7 more custom marketplace skills)
  }
}
```

### Skill Tool Description (only shows Anthropic skills)
The Skill tool's `<available_skills>` section only lists skills from `plugin:example-skills@anthropic-agent-skills`, not from custom marketplaces.

## Filesystem Verification
```bash
$ ls ~/.claude/plugins/marketplaces/
anthropic-agent-skills/
netresearch-ai-marketplace/
netresearch-claude-code-marketplace/

$ ls ~/.claude/plugins/marketplaces/netresearch-claude-code-marketplace/skills/
agents/
netresearch-branding/
typo3-conformance/
typo3-core-contributions/
typo3-ddev/
typo3-docs/
typo3-testing/
```

All skills exist on the filesystem with proper SKILL.md files and are correctly registered.

## Proposed Solution
The Skill tool should:
1. Read from `~/.claude/plugins/installed_plugins.json`
2. Scan all registered plugin paths for SKILL.md files
3. Parse and expose all installed skills in `<available_skills>`
4. Not hardcode or limit to only Anthropic's example-skills marketplace

## Workaround
Currently, users must manually tell Claude about installed skills, which defeats the purpose of the marketplace system.

## Related Files
- `~/.claude/plugins/installed_plugins.json` - Registry of installed plugins
- `~/.claude/plugins/known_marketplaces.json` - List of known marketplaces
- `~/.claude/plugins/marketplaces/*/skills/*/SKILL.md` - Individual skill definitions

## User Experience Impact
This bug creates significant friction:
1. User installs marketplace skills expecting them to "just work"
2. Claude has no awareness of these skills
3. User must debug and discover the skills aren't exposed
4. User loses confidence in the marketplace system
5. Skills remain unused despite proper installation

---

**Priority**: High - Core marketplace functionality is broken
**Category**: Skills/Plugins System
**Reproducibility**: 100% - Affects all custom marketplace skills
