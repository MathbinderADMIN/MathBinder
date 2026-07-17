# MathBinder Development Rules

## Mission

MathBinder exists to help students, parents, and teachers quickly find, learn, practice, and master mathematics through organized notebook-style lessons.

Every development decision should improve:

- clarity
- speed
- consistency
- usability
- maintainability

---

# Core Principles

## 1. Never duplicate code.

If functionality already exists, reuse it.

If similar code appears twice, refactor it.

---

## 2. One source of truth.

Each lesson should have one location for:

- Teach It
- At a Glance
- Common Questions
- Watch It
- Practice It
- My Math Notes
- Real Life Math
- Did You Know?

Never store the same information in multiple places.

---

## 3. Mobile first.

Every page must work on:

- phones
- tablets
- laptops
- desktops

---

## 4. Fast pages.

Avoid unnecessary JavaScript.

Avoid duplicate CSS.

Reuse templates.

---

## 5. WordPress standards.

Follow WordPress coding standards whenever possible.

Sanitize inputs.

Escape outputs.

Never trust user input.

---

## 6. Build reusable components.

Anything that can become a reusable template should become one.

Examples:

- Watch It
- Practice It
- Common Questions
- Notes
- Hero sections

---

## 7. Preserve notebook style.

MathBinder's identity is:

- notebook
- binder
- handwritten feel
- colorful tabs
- easy navigation

Do not redesign unless requested.

---

## 8. Git workflow

Every feature should be:

Plan

Build

Test

Commit

Document

---

## 9. Documentation

Major architectural changes must be documented.

---

## 10. AI Instructions

Before writing code:

Search the project for existing functionality.

Reuse before rewriting.

Never remove working functionality without approval.

Explain proposed changes before implementation.

Keep files organized by responsibility.

Avoid large monolithic functions.

Favor readable code over clever code.
