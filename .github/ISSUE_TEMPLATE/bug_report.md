---
name: Bug report
about: Submit an error
title: "[X.x] What does happen that is considered an error or bug?"
labels: bug
assignees: DarkGhostHunter

---

## Checklist
- [ ] I have checked my stack trace and logs.
- [ ] I can reproduce this in isolation.
- [ ] I can suggest a workaround or PR.

## Dependencies
- PHP: Exact PHP version and platform you're using, like "8.0.2 Linux"
- Laravel: Exact version of Laravel you're using, like "9.0.2"

## Description
A clear and concise description of what the bug is.

## Reproduction
Point a repository where this can be reproduced, or just paste the code to assert in a test, like this:

```php
public function test_this_breaks(): void
{
    $test = Laragear::make()->break();
    
    static::assertFalse($test);
}
```

## Expected behavior
A clear and concise description of what you expected to happen.

## Stack Trace
Having the stack trace helps to deal with _what triggered what_. You may hide sensible information.

**The better the description of the bug has, the faster it can be resolved.**