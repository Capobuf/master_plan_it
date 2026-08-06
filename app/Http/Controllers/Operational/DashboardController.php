<?php

namespace App\Http\Controllers\Operational;

use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Reporting\Queries\EconomicDatasetQuery;
use App\Domain\Reporting\Queries\TenantDashboardQuery;
use App\Http\Controllers\Controller;
use App\Models\PlanningYear;
use App\Support\Formatting\MoneyFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, EconomicDatasetQuery $datasetQuery, EconomicEngine $engine, TenantDashboardQuery $dashboard): View
    {
        $actor=$this->actor($request);$context=$this->tenantContext($request);
        $years=PlanningYear::query()->where('tenant_id',$context->tenantId)->orderBy('year_label')->get();
        $requested=$request->query('year');$selected=is_numeric($requested)?$years->firstWhere('id',(int)$requested):null;
        if($requested!==null&&!$selected){abort(404);}if(!$selected){$currentYear=(int)now($context->timezone)->year;$selected=$years->first(fn($year)=>(bool)$year->active&&(int)$year->year_label===$currentYear)??$years->firstWhere('active',true)??$years->sortByDesc('year_label')->first();}
        $props=['yearOptions'=>$years->map(fn($y)=>['value'=>(int)$y->id,'label'=>(string)$y->year_label,'active'=>(bool)$y->active])->values()->all(),'selectedYear'=>$selected?->id,'summary'=>null,'charts'=>['monthly'=>['labels'=>[],'datasets'=>[]],'byType'=>['labels'=>[],'datasets'=>[]],'byCostCenter'=>['labels'=>[],'datasets'=>[]]],'topCostCenters'=>[],'recentExpenses'=>[],'generatedExpensesToConfirm'=>[],'activeContracts'=>[],'upcomingContractEvents'=>[],'hasEconomicData'=>false,'generatedAt'=>CarbonImmutable::now('UTC')->toIso8601String(),'stale'=>false,'error'=>null];
        if(!$selected){return view('operational.dashboard', $props);}
        $dataset=$datasetQuery->execute($actor,$context,(int)$selected->id);$calculated=$engine->calculate($dataset);$amounts=$calculated['summary']->amounts;
        $props['summary']=['officialBasis'=>$calculated['summary']->officialBasis]+array_map(fn($v)=>MoneyFormatter::format($v,$context->currencyCode),$amounts);
        $chart=fn(array $values,string $label)=>['labels'=>array_keys($values),'datasets'=>[['label'=>$label,'data'=>array_map(fn($v)=>(float)$v,array_values($values))]]];
        $props['charts']=['monthly'=>$chart($calculated['monthly'],'Current position'),'byType'=>$chart($calculated['byType'],'By type'),'byCostCenter'=>$chart($calculated['byCostCenter'],'By cost center')];
        $props['topCostCenters']=collect($calculated['byCostCenter'])->sortDesc()->take(8)->map(fn($v,$k)=>['label'=>$k,'value'=>MoneyFormatter::format($v,$context->currencyCode)])->values()->all();
        $props['hasEconomicData']=$dataset->lines!==[];
        $props=array_merge($props,$dashboard->presentation($context,(int)$selected->id));
        return view('operational.dashboard', $props);
    }
}
