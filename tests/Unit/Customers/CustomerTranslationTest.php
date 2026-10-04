<?php

namespace Tests\Unit\Customers;

use Tests\TestCase;

class CustomerTranslationTest extends TestCase
{
    public function test_customer_labels_are_available_in_all_supported_locales(): void
    {
        $expected = [
            'eu' => ['Bezeroak', 'Bezero berria', 'Editatu bezeroa', ':Attribute eremua nahitaezkoa da.', ':Attribute dagoeneko hartua izan da.'],
            'es' => ['Clientes', 'Nuevo cliente', 'Editar cliente', 'El campo :attribute es obligatorio.', 'El campo :attribute ya está en uso.'],
            'fr' => ['Clients', 'Nouveau client', 'Modifier le client', 'Le champ :attribute est obligatoire.', 'Le champ :attribute a déjà été pris.'],
            'en' => ['Customers', 'New customer', 'Edit customer', 'The :attribute field is required.', 'The :attribute has already been taken.'],
        ];

        foreach ($expected as $locale => [$customers, $newCustomer, $editCustomer, $required, $unique]) {
            app()->setLocale($locale);

            $this->assertSame($customers, __('Customers'));
            $this->assertSame($newCustomer, __('New customer'));
            $this->assertSame($editCustomer, __('Edit customer'));
            $this->assertSame($required, __('validation.required'));
            $this->assertSame($unique, __('validation.unique'));
        }
    }

    public function test_basque_is_the_default_customer_locale(): void
    {
        app()->setLocale('eu');

        $this->assertSame('Bezeroak', __('Customers'));
        $this->assertSame('Bezero berria', __('New customer'));
        $this->assertSame('Editatu bezeroa', __('Edit customer'));
    }
}
