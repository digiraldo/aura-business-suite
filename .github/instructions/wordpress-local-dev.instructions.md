---
description: "Use when running local WordPress QA, browser tests, wp-admin navigation, or import wizard validation in this project. Enforces the correct local admin URL and secure auth handling."
name: "WordPress Local Dev Access Rules"
---
# WordPress Local Dev Access Rules

- Default local admin URL for this project: `https://diserwp.test/wp-admin`.
- If a task needs wp-admin navigation or E2E checks, open this URL first.
- If a page fails on `localhost`, `127.0.0.1`, or `diserwp.local`, switch to `https://diserwp.test`.
- Do not store passwords, tokens, or secrets in repository files, instructions, prompts, or memories.
- For authenticated browser tests, ask the user to sign in directly in the browser if session is missing.
- Never print or persist plaintext credentials in logs, patches, or generated docs.
- When reporting test results, include tested URL, page slug, and outcome for each flow.
