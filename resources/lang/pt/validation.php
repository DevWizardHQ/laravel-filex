<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Filex Validation Language Lines (Portuguese)
    |--------------------------------------------------------------------------
    |
    | These language lines contain the default error messages used by
    | the Filex validation rules. Some of these rules have multiple versions
    | such as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'filex_mimes' => 'O :attribute deve ser um arquivo do tipo: :values.',
    'filex_mimetypes' => 'O arquivo :attribute deve ser do tipo: :values.',
    'filex_min' => 'O :attribute deve ter pelo menos :min kilobytes.',
    'filex_max' => 'O :attribute não pode ser maior que :max kilobytes.',
    'filex_size' => 'O :attribute deve ter exatamente :size kilobytes.',
    'filex_dimensions' => 'O :attribute tem dimensões de imagem inválidas.',
    'filex_image' => 'O :attribute deve ser uma imagem.',
    'filex_file' => 'O :attribute deve ser um upload de arquivo válido.',

    // Additional Filex validation rule messages
    'temp_file' => 'O :attribute deve ser um arquivo temporário Filex válido.',
    'file_not_found_or_expired' => 'O arquivo :attribute não foi encontrado ou expirou.',
    'file_not_found' => 'O arquivo :attribute não foi encontrado.',
    'file_not_readable' => 'O arquivo :attribute não é legível.',
    'must_be_image' => 'O :attribute deve ser uma imagem.',
    'invalid_image_dimensions' => 'O :attribute tem dimensões de imagem inválidas.',
    'min_width' => 'O :attribute tem dimensões de imagem inválidas. A largura mínima é :value px.',
    'max_width' => 'O :attribute tem dimensões de imagem inválidas. A largura máxima é :value px.',
    'min_height' => 'O :attribute tem dimensões de imagem inválidas. A altura mínima é :value px.',
    'max_height' => 'O :attribute tem dimensões de imagem inválidas. A altura máxima é :value px.',
    'exact_width' => 'O :attribute tem dimensões de imagem inválidas. A largura deve ser exatamente :value px.',
    'exact_height' => 'O :attribute tem dimensões de imagem inválidas. A altura deve ser exatamente :value px.',
    'aspect_ratio' => 'O :attribute tem dimensões de imagem inválidas. A proporção deve ser :value.',
    'file_size_exact' => 'O :attribute deve ter exatamente :size.',
    'file_too_large' => 'O :attribute não pode ser maior que :max.',
    'file_too_small' => 'O :attribute deve ter pelo menos :min.',
    'invalid_mime_type' => 'O :attribute deve ser um arquivo do tipo: :values.',
    'invalid_mimetypes' => 'O arquivo :attribute deve ser do tipo: :values.',
    'invalid_upload' => 'O :attribute deve ser um upload de arquivo válido.',

    // Error messages that might be used in validation context
    'file_content_mismatch' => 'O conteúdo do arquivo :attribute não corresponde ao tipo esperado.',
    'file_signature_validation_failed' => 'A validação da assinatura do arquivo :attribute falhou.',
    'file_content_validation_failed' => 'A validação do conteúdo do arquivo :attribute falhou.',
    'file_security_validation_failed' => 'O arquivo :attribute falhou na validação de segurança.',
    'file_validation_failed' => 'A validação do arquivo :attribute falhou.',
    'invalid_file_path' => 'Caminho de arquivo inválido para :attribute. Exclusão de arquivo não permitida.',

    /*
    |--------------------------------------------------------------------------
    | Custom Attribute Names
    |--------------------------------------------------------------------------
    |
    | These language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
