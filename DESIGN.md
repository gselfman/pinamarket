# Pina Market design direction

Status: design concept, 2026-11-14. Public visual direction. Figma: https://www.figma.com/design/rIyrCBWcvMRjqTlE4RhHtA.

## Purpose
Merchant-specific gift cards for goods and services abroad, purchased in RUB. Two audiences: individual buyers and foreign merchant companies. Buyer headline: «Покупайте у зарубежных партнёров с оплатой в рублях». Merchant headline: «Продавайте клиентам из России через карты вашей компании».

## Visual language
Use the existing Pina logo from dist/pina-logo.png. Use Manrope with Cyrillic; the existing website already uses this family. Warm off-white canvas, white cards, forest green action, pale lime accents, dark green text. Large readable sums and generous spacing. Gift-card graphics never look like bank cards. No neon, glassmorphism, crypto imagery, fake merchants or invented performance statistics.

Tokens: primary #1E7A46; dark #163B2A; background #F6F8F2; surface #FFFFFF; muted #526259; border #D8E2D8; accent #DCEEBA; error #B42318. On dark use white; on pale lime use dark. Standard text contrast >=4.5:1. Spacing in 4/8px steps, radius 12/16/24px. Desktop 1440px, content max1200px; mobile390px with24px padding and360px verification. Body16–18px /1.5, desktop display56–64px, mobile34–38px. Controls >=44px hit area.

## Components and behaviour
Reusable Button, Input, Badge, GiftCard, OrderSummary and FAQ rows. Buttons: default, hover, focus, disabled, loading. Forms keep visible labels, inline errors and typed values after failure. Never communicate status by colour alone. Native keyboard focus and zoom remain available.


## Motion
150–200ms hover/focus, <=250ms panels, <=350ms single entrance. Prefer opacity and transform. Respect reduced motion and show final state immediately. No perpetual card rotation, scroll hijack, magnetic financial controls or animated final sums.

## Graphic assets
Unique outline icon family: 24 signs, 24px grid,1.75px round strokes. Contact sheet6×4 and SVG symbol sprite live in design/. Original geometric paths are editable. Raster illustration prompts and production checks are in SPEC sections12–13. Raster art remains decorative; UI labels and amounts are real text.

## Implementation
Existing PHP8.2+/MySQL/Apache stack. Do not introduce React or require a production build server to use a component reference. UI examples use labelled demo data and cannot take real payments.

## Applied references
UI UX Pro Max financial-services minimal direction, accessibility and restrained motion: https://github.com/nextlevelbuilder/ui-ux-pro-max-skill. Gift-card search returned gaming typography and was rejected as unsuitable. Palette adapted to Pina identity; no claim to use generated default colours unchanged.

Awesome DESIGN.md provided the approach of one explicit project design source: https://github.com/VoltAgent/awesome-design-md. Read its publicly available NerdWallet analysis for green-on-light editorial clarity; this is an original Pina direction, not a copied brand system. 21st.dev and Aceternity are component references, not installed runtime dependencies. MotionSites reviewed as an optional prompt reference; no paid template purchased or incorporated.

## Handoff gate
Editable Figma layers, shared components and tokens, no full-screen raster; desktop/mobile layouts and key payment/code states; 24 legible icons; complete issuer and payment disclosures before purchase; no unsupported speed, coverage or refund promises. Operational specification is maintained separately.
