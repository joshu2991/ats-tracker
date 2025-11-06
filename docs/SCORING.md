# Scoring Algorithm

## Overall Score Calculation

The overall score is calculated using a weighted average:

- **Parseability**: 25% (rule-based technical checks)
- **Format & Structure**: 25% (AI + rule-based)
- **Keywords & Skills**: 25% (AI + rule-based)
- **Contact Info**: 10% (AI + rule-based)
- **Content Quality**: 15% (AI + rule-based)

## Parseability Score (0-100)

Starts at 90 points, penalties applied for:
- Scanned images: -30
- Tables detected: -30
- Multi-column layouts: -25
- Missing contact info: -25
- Date placeholders: -20
- Missing dates: -25
- Missing name: -20
- Missing summary: -10
- Length issues: -10 to -15
- Insufficient bullets: -10 to -20
- Lack of metrics: -15

## Category Scores (0-100)

Each category is scored by AI analysis with adjustments from parseability checks:
- **Format**: Section presence, structure, dates, formatting
- **Keywords**: Technical keyword density and relevance
- **Contact**: Completeness and optimal location (first 300 chars)
- **Content**: Action verbs, quantifiable achievements, bullet points

## Issue Categorization

- **Critical** (< 30 score): Unparseable, no contact, critical format issues
- **Warnings** (30-60 score): Contact in header, few keywords, minor issues
- **Improvements** (60-100 score): Could use more keywords, stronger verbs, enhancements

## Scoring Breakdown

- **Green (70-100)**: Excellent - Your resume is ATS-ready
- **Amber (50-69)**: Good - Minor improvements needed
- **Rose (0-49)**: Needs Improvement - Significant changes recommended

