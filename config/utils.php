<?php
/**
 * Utilitários globais para o portal BuscaCNPJ Grátis
 */

/**
 * Converte nomes para Title Case, respeitando preposições em português.
 * Útil para formatar nomes de cidades e empresas.
 */
function titleCase($string) {
    $small_words = ['de', 'da', 'do', 'das', 'dos', 'e', 'em', 'para'];
    $words = explode(' ', mb_strtolower($string));
    
    foreach ($words as $i => $word) {
        if ($i > 0 && in_array($word, $small_words)) {
            continue;
        }
        $words[$i] = mb_convert_case($word, MB_CASE_TITLE, "UTF-8");
    }
    
    return implode(' ', $words);
}

/**
 * Formata valores monetários de forma amigável (K, M, B, T)
 */
function format_money_friendly($val) {
    $val = (float)$val;
    if ($val >= 1000000000000) {
        return 'R$ ' . number_format($val / 1000000000000, 2, ',', '.') . ' Trilhões';
    } elseif ($val >= 1000000000) {
        return 'R$ ' . number_format($val / 1000000000, 2, ',', '.') . ' Bilhões';
    } elseif ($val >= 1000000) {
        return 'R$ ' . number_format($val / 1000000, 2, ',', '.') . ' Milhões';
    }
    return 'R$ ' . number_format($val, 2, ',', '.');
}
