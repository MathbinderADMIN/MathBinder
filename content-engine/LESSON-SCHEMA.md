# Lesson Schema and Renderer

## Overview

The new lesson schema is a standalone data structure for representing a complete MathBinder lesson without relying on WordPress templates or plugin-specific hooks.

## Schema structure

The schema supports these lesson sections:

- title
- overview
- teach_it
- at_a_glance
- common_questions
- watch_it
- practice_it
- my_math_notes
- real_life_math
- did_you_know

Each section can store either a plain string or an array of values, and the schema normalizes them into a predictable structure.

## Renderer behavior

The renderer is intentionally independent of WordPress. It:

- returns HTML instead of echoing it by default
- escapes all rendered output
- supports strings and arrays safely
- renders each section independently
- uses an explicit extension point for future section types

## Extension points

Future lesson sections can be added by registering a renderer with the renderer class. This keeps the rendering pipeline extensible while avoiding arbitrary execution from lesson input.

## Future WordPress compatibility

This layer is designed to be future-compatible with WordPress. The schema and renderer operate on plain PHP data and HTML output, so they can later be wrapped by a WordPress adapter without changing the core lesson representation.

## Notes

The implementation is intentionally additive and does not modify the existing content-pack plugin, templates, or core plugin files.
