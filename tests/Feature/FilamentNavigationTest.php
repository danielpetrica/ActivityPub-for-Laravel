<?php

use App\Filament\Resources\CaseStudies\CaseStudyResource;
use App\Filament\Resources\Services\ServiceResource;

it('groups Services and Case Studies under Local services', function (): void {
    // Assert resources report the expected navigation group label.
    expect(ServiceResource::getNavigationGroup())->toBe('Local services');
    expect(CaseStudyResource::getNavigationGroup())->toBe('Local services');

});
