@extends('errors.layout')

@section('title', 'Page Introuvable (404)')
@section('badge', 'Erreur 404 • Lien Introuvable')
@section('heading', 'Ce lien est introuvable')
@section('description', 'Le lien ou la page que vous recherchez a peut-être été déplacé, supprimé ou n\'a jamais existé dans ce coffre-fort.')

@section('icon')
<style>
    :root {
        --icon-bg: rgba(99, 102, 241, 0.12);
        --icon-color: #818cf8;
        --icon-border: rgba(99, 102, 241, 0.3);
        --glow-color: rgba(99, 102, 241, 0.2);
    }
</style>
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
    <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
    <line x1="2" y1="2" x2="22" y2="22" stroke-width="2.2" stroke="#ef4444"/>
</svg>
@endsection
