Git Easy Push — Frontend

This small static frontend helps you build the exact shell command to run the `scripts/git-easy-push.sh` helper.

Files
- index.html — the UI
- styles.css — minimal styling
- app.js — builds the command and copies it to clipboard

Usage
1. Open `frontend/index.html` in your browser (double-click or open with file://). For clipboard access prefer serving it over HTTP.

2. Fill in the fields (commit message, branch, remote URL, file paths) and click "Build Command".

3. Click "Copy to clipboard" and paste the command into your terminal in the repository root.

Serve locally (recommended) using Python's HTTP server:

```bash
# from repo root
python3 -m http.server --directory frontend 8000
# then open http://localhost:8000 in your browser
```

Security
- The frontend only constructs a shell command; it does not run any commands on your machine.
- Review the built command before running it.

Next steps
- Add a small Node/Express preview that runs in a sandbox (advanced)
- Add a Makefile target to `Makefile` to serve the frontend easily
