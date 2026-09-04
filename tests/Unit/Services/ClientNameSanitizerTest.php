<?php

namespace Tests\Unit\Services;

use App\Services\ClientNameSanitizer;
use Tests\TestCase;

class ClientNameSanitizerTest extends TestCase
{
    /** The reported bug: a leaked MRZ '<<' separator inside first_name. */
    public function test_strips_leaked_mrz_double_separator(): void
    {
        // client #1673 (citycomm) actual value from gemma3:27b
        $this->assertSame('DOAA ABDELMONEM', ClientNameSanitizer::clean('DOAA<<ABDELMONEM'));
    }

    public function test_strips_single_and_trailing_filler(): void
    {
        $this->assertSame('MOHAMED MOKHTAR FARRARA', ClientNameSanitizer::clean('MOHAMED<MOKHTAR<FARRARA<<<<<'));
    }

    public function test_collapses_whitespace_and_trims(): void
    {
        $this->assertSame('AL SAYED', ClientNameSanitizer::clean('  AL   SAYED  '));
    }

    public function test_clean_name_passes_through_unchanged(): void
    {
        $this->assertSame('DOAA', ClientNameSanitizer::clean('DOAA'));
    }

    public function test_null_stays_null(): void
    {
        $this->assertNull(ClientNameSanitizer::clean(null));
    }

    public function test_blank_or_filler_only_becomes_null(): void
    {
        // nullable columns (middle_name) should stay null, not become ''
        $this->assertNull(ClientNameSanitizer::clean('   '));
        $this->assertNull(ClientNameSanitizer::clean('<<<<<'));
    }
}
