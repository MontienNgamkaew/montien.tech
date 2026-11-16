#!/usr/bin/env python3
"""Count rows in this project's mysqldump VALUES format without importing SQL.

Prints table names and counts only. Does not execute SQL or print row contents.
"""
import json
import re
import sys
from pathlib import Path


def count_rows(sql):
    counts = {name: 0 for name in re.findall(r'^CREATE TABLE `([^`]+)`', sql, re.M)}
    for match in re.finditer(r'^INSERT INTO `([^`]+)` VALUES\s*', sql, re.M):
        name = match.group(1)
        if name not in counts:
            raise ValueError('INSERT without CREATE TABLE')
        position = match.end()
        depth = 0
        quote = None
        ended = False
        while position < len(sql):
            char = sql[position]
            if quote:
                if char == '\\':
                    position += 2
                    continue
                if char == quote:
                    if position + 1 < len(sql) and sql[position + 1] == quote:
                        position += 2
                        continue
                    quote = None
            elif char in "'\"":
                quote = char
            elif char == '(':
                if depth == 0:
                    counts[name] += 1
                depth += 1
            elif char == ')':
                depth -= 1
                if depth < 0:
                    raise ValueError('Unbalanced VALUES')
            elif char == ';' and depth == 0:
                ended = True
                break
            position += 1
        if not ended or quote or depth:
            raise ValueError('Incomplete INSERT')
    # Refuse unsupported INSERT variants instead of silently reporting wrong counts.
    if len(re.findall(r'^INSERT INTO ', sql, re.M)) != len(re.findall(r'^INSERT INTO `[^`]+` VALUES\s*', sql, re.M)):
        raise ValueError('Unsupported INSERT format')
    return counts


if __name__ == '__main__':
    backup = Path(sys.argv[1])
    result = {}
    for label in ['portal', 'pnpgo', 'academix']:
        sql = (backup / (label + '.sql')).read_text()
        if not re.search(r'^-- Dump completed on ', sql, re.M):
            raise ValueError('Dump has no completion marker')
        result[label] = count_rows(sql)
    print(json.dumps(result, indent=2))
