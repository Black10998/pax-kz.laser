# Development smoke tests

These files are for **development and CI only**. They are not loaded by WordPress.

## PHP (requires PHP 8+ with ext-dom)

From the plugin root:

```bash
php tests/export-vector-text-regression.php
php tests/visual-export-smoke.php
php tests/scene-export-smoke.php
```

## Node (Fabric export structure)

```bash
cd tests/node
npm install
npm test
```
