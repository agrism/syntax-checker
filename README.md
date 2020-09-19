# PHP syntax checker

Usage in current project:
- to run checker: `php SyntaxCheck.php` 
- to run checker and commit: `php SyntaxCheck.php commit`

or use alias:
- create alias: add `check() {php "/absolute/paths/to/file/SyntaxCheck.php" $1}` to file  ~/.zshrc
- call from any other project:
    - to run checker: `check` 
    - to run checker and commit: `check commit`