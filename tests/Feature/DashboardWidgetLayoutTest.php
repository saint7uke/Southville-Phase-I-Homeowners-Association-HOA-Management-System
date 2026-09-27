<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Widgets\ComplaintStatusChart;
use Filament\Facades\Filament;
use Tests\TestCase;

final class DashboardWidgetLayoutTest extends TestCase
{
    public function test_complaint_status_breakdown_is_the_final_full_width_admin_widget(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $widgets = Filament::getWidgets();

        $this->assertSame(ComplaintStatusChart::class, array_pop($widgets));
        $this->assertSame('full', (new ComplaintStatusChart)->getColumnSpan());
    }
}
