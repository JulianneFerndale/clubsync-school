<?php

/*
 * The Vice President for Administration, as room booking letters address and name
 * them (POLICY.md §Event Creation). The office's holder changes with appointments, so
 * the name is configuration rather than code; the VPA account that signs in to
 * approve letters may be run by the SSG and need not carry this name.
 */
return [
    // Printed in bold under the date, and again under "Approved by:".
    'name' => env('VPA_NAME', 'Dr. Mary Jane B. Omandam, RGC'),

    // "Dear {salutation},"
    'salutation' => env('VPA_SALUTATION', 'Dr. Omandam'),

    'title' => 'Vice President for Administration',
];
