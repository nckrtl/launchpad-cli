---
name: release-version
description: Release a new version of the project. Use when the user wants to release, tag, or publish a new version.
allowed-tools: Bash(git:*), Bash(gh:*), Read, Edit, Glob, Grep
---

# Release New Version

## Instructions

When releasing a new version, follow these steps:

### 1. Check Current State

```bash
# Check for uncommitted changes
git status

# Get current version tags
git tag --sort=-version:refname | head -5

# View recent commits since last tag
git log $(git describe --tags --abbrev=0)..HEAD --oneline
```

### 2. Determine Version Number

Follow semantic versioning (MAJOR.MINOR.PATCH):
- **MAJOR**: Breaking changes
- **MINOR**: New features, backwards compatible
- **PATCH**: Bug fixes, backwards compatible

### 3. Review and Update Documentation

**IMPORTANT**: Before releasing, ensure documentation is up to date.

#### 3.1 Check for New/Changed Commands

Review all commands in `app/Commands/` and compare with documentation:

1. Read `app/Commands/` directory to list all commands
2. Read `README.md` and check the Commands table
3. Read `CLAUDE.md` and check:
   - Architecture section (Commands list)
   - Commands table
   - Test Coverage section

#### 3.2 Update Documentation if Needed

For each command file, verify:
- Command is listed in README.md Commands table
- Command is listed in CLAUDE.md Commands table
- Command is in CLAUDE.md Architecture tree
- Command tests are mentioned in Test Coverage

If any documentation is missing or outdated:
1. Update README.md with new/changed commands
2. Update CLAUDE.md with:
   - New command in Architecture tree
   - New command in Commands table
   - Updated Test Coverage section

#### 3.3 Verify Documentation Accuracy

Ensure these sections are synchronized:
- README.md Commands table matches actual CLI commands
- CLAUDE.md reflects current project structure
- All new features from commits since last tag are documented

### 4. Commit Changes (if needed)

If there are uncommitted changes (including documentation updates), commit them first:

```bash
git add -A
git commit -m "Your commit message"
```

### 5. Create and Push Tag

```bash
# Create annotated tag
git tag -a vX.Y.Z -m "vX.Y.Z - Brief description"

# Push commits and tags
git push && git push --tags
```

### 6. Create GitHub Release

Always use the gh CLI to create the release:

```bash
gh release create vX.Y.Z --title "vX.Y.Z" --notes "$(cat <<'EOF'
## What's New

- Feature 1
- Feature 2

## Bug Fixes

- Fix 1
EOF
)"
```

### 7. Verify Release

```bash
# Confirm release was created
gh release view vX.Y.Z
```

## Documentation Checklist

Before tagging, confirm:

- [ ] All commands in `app/Commands/` are documented in README.md
- [ ] All commands in `app/Commands/` are documented in CLAUDE.md
- [ ] CLAUDE.md Architecture tree is current
- [ ] CLAUDE.md Test Coverage section lists all command tests
- [ ] New features from recent commits are documented
- [ ] JSON output examples are up to date (if changed)

## Notes

- Always use `gh release create` for GitHub releases
- Include meaningful release notes summarizing changes
- Link to related issues/PRs when applicable
- Documentation must be updated BEFORE creating the tag
