# Manify Command — Implementation Plan

## Phase 1 — Output correctness (must fix)

**Goal:** Generated markdown is readable and syntactically correct.

### 1.1 Fix `get_clean_examples()` indentation (C2)

`array_map('trim', ...)` strips all leading whitespace, destroying the visual structure of WP-CLI example blocks.

**File:** `src/Manify_Command.php` — `get_clean_examples()`

**Fix:** Replace per-line `trim()` with dedent: compute the minimum leading-space count across non-blank lines and strip only that many characters from every line.

```php
private function dedent( string $text ): string {
    $lines   = explode( "\n", $text );
    $indents = [];
    foreach ( $lines as $line ) {
        if ( '' === trim( $line ) ) {
            continue;
        }
        preg_match( '/^( *)/', $line, $m );
        $indents[] = strlen( $m[1] );
    }
    if ( empty( $indents ) ) {
        return $text;
    }
    $min = min( $indents );
    return implode( "\n", array_map(
        fn( $l ) => '' === trim( $l ) ? $l : substr( $l, $min ),
        $lines
    ) );
}
```

Replace `get_clean_examples()` to call `$this->dedent()` instead of the `trim` map.

---

### 1.2 OPTIONS section: remove code fence (D1)

OPTIONS uses a structured WP-CLI list (`[--flag=<val>]\n: description`). Wrapping it in ` ``` ` prevents markdown renderers from formatting it. EXAMPLES should remain fenced.

**File:** `src/Manify_Command.php` — around line 249

**Fix:** Emit OPTIONS content verbatim (no `get_wrapped()`). Only EXAMPLES goes through `get_wrapped()`.

```php
if ( ! empty( $options ) ) {
    $options           = trim( str_replace( '## OPTIONS', '', $options ) );
    $markdown_content .= "## OPTIONS\n\n{$options}\n\n";
}
```

---

### 1.3 Sanitize output filename (D2)

`$command_slug` (e.g. `myplugin run`) produces `myplugin run.md` — a filename with a space.

**File:** `src/Manify_Command.php` — line 266

**Fix:**

```php
$filename    = preg_replace( '/[^a-z0-9_-]+/i', '-', $command_slug );
$output_file = rtrim( $destination, '/' ) . "/{$filename}.md";
```

---

### 1.4 Dead returns after `WP_CLI::error()` (F1)

`WP_CLI::error()` exits by default. The `return false;` statements that follow it are unreachable and misleading.

**File:** `src/Manify_Command.php` — `validate_composer_file()`

**Fix:** Drop all `return` statements that immediately follow `WP_CLI::error()`.

---

## Phase 2 — Docblock parsing quality (should fix)

**Goal:** All standard WP-CLI docblock sections render correctly.

### 2.1 Section-aware longdesc parsing (C1)

Current `explode('## EXAMPLES', ...)` only handles one section boundary. Docblocks with `## OPTIONS`, `## SUBCOMMANDS`, `## NOTES`, etc. are all lumped into `$options`.

**File:** `src/Manify_Command.php`

**Fix:** Add a `split_into_sections()` method that scans for `/^## ([A-Z ]+)$/m` markers and returns a keyed array. Then render each known section (`OPTIONS`, `EXAMPLES`, others) appropriately.

```php
private function split_into_sections( string $longdesc ): array {
    $sections = [];
    $current  = '_preamble';
    foreach ( explode( "\n", $longdesc ) as $line ) {
        if ( preg_match( '/^## ([A-Z ]+)$/', $line, $m ) ) {
            $current = trim( $m[1] );
        } else {
            $sections[ $current ][] = $line;
        }
    }
    return array_map( fn( $lines ) => implode( "\n", $lines ), $sections );
}
```

---

### 2.2 Consistent blank-line spacing (D3)

Blank-line counts between heading, OPTIONS, EXAMPLES, and the trailing separator vary depending on which sections are present.

**Fix:** After Phase 2.1 lands, route every section through a single formatter that trims inner content and appends exactly `\n\n`.

---

## Phase 3 — Code hygiene (cleanup)

**Goal:** Remove noise; make the class easier to extend.

### 3.1 Class decomposition (F2) — defer

Split `Manify_Command` into `ComposerConfigReader`, `CommandReflector`, `DocblockParser`, `MarkdownRenderer` only after Phases 1–2 are done. Not worth doing in isolation.

---

## Execution order

| Phase | Items | Complexity |
|---|---|---|
| 1 | 1.1, 1.2, 1.3, 1.4 | Low — each is a self-contained method change |
| 2 | 2.1, 2.2 | Medium — new parsing logic, touches rendering path |
| 3 | 3.1, 3.2 | Low / High |

Start Phase 1. Each item is independent and can be a separate commit.
