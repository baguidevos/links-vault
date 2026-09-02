@extends('errors.layout')

@section('title', 'Accès Refusé (403)')
@section('badge', 'Erreur 403 • Accès Restreint')
@section('heading', 'Accès Refusé au Coffre-fort')
@section('description', 'Vous ne disposez pas des permissions requises pour accéder à cet espace ou modifier cette ressource. Veuillez contacter l\'administrateur de l\'équipe.')

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
    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
    <circle cx="12" cy="16" r="1.5" fill="currentColor"/>
</svg>
@endsection
