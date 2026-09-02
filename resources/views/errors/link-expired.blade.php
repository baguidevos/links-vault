@extends('errors.layout')

@section('title', 'Lien de Partage Expiré')
@section('badge', 'Lien Expiré • Accès Clôturé')
@section('heading', 'Ce lien de partage a expiré')
@section('description', 'La période de validité de ce lien temporaire est désormais écoulée. Veuillez demander au propriétaire du coffre-fort de générer une nouvelle invitation.')

@section('icon')
<style>
    :root {
        --icon-bg: rgba(245, 158, 11, 0.12);
        --icon-color: #fbbf24;
        --icon-border: rgba(245, 158, 11, 0.3);
        --badge-bg: rgba(245, 158, 11, 0.15);
        --badge-text: #f59e0b;
        --badge-border: rgba(245, 158, 11, 0.3);
        --glow-color: rgba(245, 158, 11, 0.2);
    }
</style>
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="12" cy="12" r="10"/>
    <polyline points="12 6 12 12 14 14"/>
    <line x1="4" y1="4" x2="20" y2="20" stroke="#f59e0b" stroke-width="2"/>
</svg>
@endsection
