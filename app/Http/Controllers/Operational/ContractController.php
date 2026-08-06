<?php

namespace App\Http\Controllers\Operational;

use App\Domain\Contracts\Actions\CreateContract;
use App\Domain\Contracts\Actions\DeleteContract;
use App\Domain\Contracts\Actions\DeleteContractTerm;
use App\Domain\Contracts\Actions\GenerateContractOccurrenceForYear;
use App\Domain\Contracts\Actions\ResumeAndGenerateOccurrence;
use App\Domain\Contracts\Actions\ResumeContractOccurrence;
use App\Domain\Contracts\Actions\SynchronizeContractOccurrences;
use App\Domain\Contracts\Actions\UpdateContract;
use App\Domain\Contracts\Data\SaveContractData;
use App\Domain\Contracts\Data\SaveContractTermData;
use App\Domain\Contracts\Enums\BillingCycle;
use App\Domain\Contracts\Queries\ContractDetailQuery;
use App\Domain\Contracts\Queries\ContractListQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractTerm;
use App\Policies\ContractPolicy;
use App\Support\Formatting\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class ContractController extends Controller
{
    public function index(Request $request, ContractListQuery $query): Response
    {
        $items = $query->paginate($this->actor($request), $this->tenantContext($request), (int)$request->query('page',1));
        return Inertia::render('Operational/Contracts/Index', ['contracts'=>['data'=>collect($items->items())->map(fn(Contract $c)=>$this->listProps($c))->all(),'currentPage'=>$items->currentPage(),'lastPage'=>$items->lastPage(),'perPage'=>$items->perPage(),'total'=>$items->total(),'from'=>$items->firstItem(),'to'=>$items->lastItem(),'links'=>$items->linkCollection()->all()],'abilities'=>$this->abilities($request)]);
    }

    public function create(Request $request): Response
    {
        app(ContractPolicy::class)->create($this->actor($request))->authorize();
        return Inertia::render('Operational/Contracts/Create', $this->formProps($this->tenantContext($request), null));
    }

    public function store(Request $request, CreateContract $action): RedirectResponse
    {
        $contract = $action->execute($this->actor($request), $this->tenantContext($request), $this->data($request, false), $this->correlationId($request));
        return redirect()->route('operational.contracts.show',$contract)->with('success','Contract created.');
    }

    public function show(Request $request, int $contract, ContractDetailQuery $query): Response
    {
        $context=$this->tenantContext($request); $detail=$query->find($this->actor($request),$context,$contract); $target=$detail['contract'];
        $generated=$target->expenses->filter(fn($e)=>$e->rows->contains(fn($row)=>$row->source_key!==null))->map(function($e)use($context){$row=$e->rows->first(fn($candidate)=>$candidate->source_key!==null);return ['id'=>(int)$e->id,'title'=>$e->title,'href'=>route('operational.expenses.show',$e),'state'=>$row?->confirmation_state?->value,'confirmationState'=>$row?->confirmation_state?->value,'isSystemManaged'=>(bool)$row?->is_system_managed,'sourceKey'=>(string)$row?->source_key,'occurrenceDate'=>$row?->contract_occurrence_date?->toDateString()??$row?->spend_date,'periodLabel'=>$row?->contract_occurrence_date?->format('m/Y')??substr((string)$row?->spend_date,0,7),'gross'=>MoneyFormatter::format((string)$row?->gross_amount,$context->currencyCode)];})->values()->all();
        $suppressed=collect($detail['occurrences'])->filter(fn($o)=>$o->suppressed)->map(fn($o)=>['sourceKey'=>$o->sourceKey,'occurrenceDate'=>$o->occurrenceDate,'termId'=>$o->termId])->values()->all();
        $revisionActivity=app(ContractPolicy::class)->viewRevisions($this->actor($request),$target)->allowed()?\App\Models\RevisionBatch::query()->where('tenant_id',$context->tenantId)->where('root_subject_type',$target->getMorphClass())->where('root_subject_id',$target->id)->with('actor')->orderByDesc('occurred_at')->limit(10)->get()->map(fn($batch)=>['id'=>(int)$batch->id,'operation'=>$batch->operation->value??(string)$batch->operation,'actor'=>$batch->actor?->name,'timestamp'=>$batch->occurred_at->toIso8601String(),'summary'=>$batch->reason])->all():[];
        $contractProps=$this->detailProps($target,$context->currencyCode)+['generatedExpenses'=>$generated,'suppressedOccurrences'=>$suppressed,'revisionActivity'=>$revisionActivity];
        return Inertia::render('Operational/Contracts/Show',['contract'=>$contractProps,'generatedExpenses'=>$generated,'suppressedOccurrences'=>$suppressed,'revisionActivity'=>$revisionActivity,'planningYears'=>\App\Models\PlanningYear::query()->where('tenant_id',$context->tenantId)->orderBy('year_label')->get()->map(fn($y)=>['value'=>(int)$y->year_label,'label'=>(string)$y->year_label])->all(),'deletionReasonRequired'=>(bool)$context->tenant->deletion_reason_required,'abilities'=>$this->abilities($request,$target)]);
    }

    public function edit(Request $request,int $contract): Response
    {
        $context=$this->tenantContext($request); $target=$this->contract($context,$contract)->load('terms'); app(ContractPolicy::class)->update($this->actor($request),$target)->authorize();
        return Inertia::render('Operational/Contracts/Edit',$this->formProps($context,$target));
    }

    public function update(Request $request,int $contract,UpdateContract $action): RedirectResponse
    {
        $context=$this->tenantContext($request); $action->execute($this->actor($request),$context,$this->contract($context,$contract),$this->data($request,true),$this->correlationId($request));
        return redirect()->route('operational.contracts.show',$contract)->with('success','Contract updated.');
    }

    public function destroy(Request $request,int $contract,DeleteContract $action): RedirectResponse
    {
        $v=$request->validate(['lock_version'=>['required','integer','min:1'],'deletion_reason'=>['nullable','string','max:2000']]); $context=$this->tenantContext($request);
        $action->execute($this->actor($request),$context,$this->contract($context,$contract),(int)$v['lock_version'],$v['deletion_reason']??null,$this->correlationId($request));
        return redirect()->route('operational.contracts.index')->with('success','Contract deleted.');
    }

    public function synchronize(Request $request,int $contract,SynchronizeContractOccurrences $action): RedirectResponse { $context=$this->tenantContext($request); $result=$action->execute($this->actor($request),$context,$this->contract($context,$contract),$this->correlationId($request)); return back()->with('success',"Synchronization complete: {$result['created']} created, {$result['updated']} updated."); }
    public function generate(Request $request,int $contract,int $year,GenerateContractOccurrenceForYear $action): RedirectResponse { $context=$this->tenantContext($request); $action->execute($this->actor($request),$context,$this->contract($context,$contract),$year,$this->correlationId($request)); return back()->with('success','Occurrence generated.'); }
    public function resume(Request $request,int $contract,string $sourceKey,ResumeContractOccurrence $action): RedirectResponse { $context=$this->tenantContext($request); $action->execute($this->actor($request),$context,$this->contract($context,$contract),$sourceKey,$this->correlationId($request)); return back()->with('success','Occurrence resumed.'); }
    public function resumeAndGenerate(Request $request,int $contract,string $sourceKey,ResumeAndGenerateOccurrence $action): RedirectResponse { $context=$this->tenantContext($request); $action->execute($this->actor($request),$context,$this->contract($context,$contract),$sourceKey,$this->correlationId($request)); return back()->with('success','Occurrence resumed and generated.'); }
    public function destroyTerm(Request $request,int $contract,int $term,DeleteContractTerm $action): RedirectResponse { $v=$request->validate(['lock_version'=>['required','integer','min:1'],'deletion_reason'=>['nullable','string','max:2000']]);$context=$this->tenantContext($request);$target=$this->contract($context,$contract);$targetTerm=ContractTerm::query()->where('tenant_id',$context->tenantId)->where('contract_id',$target->id)->findOrFail($term);$action->execute($this->actor($request),$context,$target,$targetTerm,(int)$v['lock_version'],$v['deletion_reason']??null,$this->correlationId($request));return back()->with('success','Contract term deleted.'); }

    private function data(Request $request,bool $update): SaveContractData
    {
        $rules=['vendor_id'=>['required','integer'],'cost_center_id'=>['required','integer'],'title'=>['required','string','max:255'],'description'=>['nullable','string'],'active'=>['required','boolean'],'renewal_date'=>['nullable','date_format:Y-m-d'],'renewal_notice_days'=>['nullable','integer','min:0'],'renewal_notes'=>['nullable','string'],'terms'=>['required','array','min:1'],'terms.*.id'=>['nullable','integer'],'terms.*.local_key'=>['required','string','max:100'],'terms.*.effective_start'=>['required','date_format:Y-m-d'],'terms.*.effective_end'=>['required','date_format:Y-m-d'],'terms.*.billing_cycle'=>['required',Rule::enum(BillingCycle::class)],'terms.*.quantity'=>['nullable','string'],'terms.*.unit_price'=>['nullable','string'],'terms.*.entered_amount'=>['required','string'],'terms.*.amount_includes_vat'=>['required','boolean'],'terms.*.vat_rate'=>['nullable','string'],'terms.*.auto_renew'=>['required','boolean'],'terms.*.lock_version'=>['nullable','integer','min:1']];if($update){$rules['lock_version']=['required','integer','min:1'];}$v=$request->validate($rules);
        $terms=array_map(fn($t)=>new SaveContractTermData(isset($t['id'])?(int)$t['id']:null,$t['local_key'],$t['effective_start'],$t['effective_end'],BillingCycle::from($t['billing_cycle']),$t['quantity']??null,$t['unit_price']??null,$t['entered_amount'],(bool)$t['amount_includes_vat'],$t['vat_rate']??'',(bool)$t['auto_renew'],isset($t['lock_version'])?(int)$t['lock_version']:null),$v['terms']);
        return new SaveContractData((int)$v['vendor_id'],(int)$v['cost_center_id'],$v['title'],$v['description']??null,(bool)$v['active'],$v['renewal_date']??null,isset($v['renewal_notice_days'])?(int)$v['renewal_notice_days']:null,$v['renewal_notes']??null,isset($v['lock_version'])?(int)$v['lock_version']:null,$terms);
    }

    private function contract(TenantContext $context,int $id): Contract { return Contract::query()->where('tenant_id',$context->tenantId)->findOrFail($id); }
    private function listProps(Contract $c): array { return ['id'=>(int)$c->id,'title'=>$c->title,'vendorName'=>$c->vendor?->name,'costCenterName'=>$c->costCenter?->name,'active'=>(bool)$c->active,'effectiveStart'=>$c->terms->min(fn($t)=>$t->effective_start?->toDateString()),'effectiveEnd'=>$c->terms->max(fn($t)=>$t->effective_end?->toDateString()),'termCount'=>(int)$c->terms_count,'generatedExpenseCount'=>(int)($c->generated_expenses_count??0),'lockVersion'=>(int)$c->lock_version]; }
    private function detailProps(Contract $c,string $currency): array { return $this->listProps($c)+['vendorId'=>(int)$c->vendor_id,'costCenterId'=>(int)$c->cost_center_id,'description'=>$c->description,'renewalDate'=>$c->renewal_date?->toDateString(),'renewalNoticeDays'=>$c->renewal_notice_days,'renewalNotes'=>$c->renewal_notes,'lockVersion'=>(int)$c->lock_version,'terms'=>$c->terms->map(fn($t)=>['id'=>(int)$t->id,'localKey'=>'term-'.$t->id,'effectiveStart'=>$t->effective_start->toDateString(),'effectiveEnd'=>$t->effective_end->toDateString(),'billingCycle'=>$t->billing_cycle->value,'quantity'=>$t->quantity,'unitPrice'=>$t->unit_price,'enteredAmount'=>$t->entered_amount,'amountIncludesVat'=>(bool)$t->amount_includes_vat,'vatRate'=>$t->vat_rate,'net'=>MoneyFormatter::format($t->net_amount,$currency),'vat'=>MoneyFormatter::format($t->vat_amount,$currency),'gross'=>MoneyFormatter::format($t->gross_amount,$currency),'autoRenew'=>(bool)$t->auto_renew,'lockVersion'=>(int)$t->lock_version])->all()]; }
    private function formProps(TenantContext $context,?Contract $contract): array { $options=fn($q)=>$q->where('tenant_id',$context->tenantId)->orderBy('name')->get(['id','name'])->map(fn($x)=>['value'=>(int)$x->id,'label'=>$x->name])->all();return ['contract'=>$contract===null?null:$this->detailProps($contract,$context->currencyCode),'vendors'=>$options(\App\Models\Vendor::query()),'costCenters'=>$options(\App\Models\CostCenter::query()),'defaults'=>['vatRate'=>$context->defaultVatRate]]; }
    private function abilities(Request $request,?Contract $contract=null): array { $p=app(ContractPolicy::class);$a=$this->actor($request);return ['create'=>$p->create($a)->allowed(),'update'=>$contract?$p->update($a,$contract)->allowed():false,'delete'=>$contract?$p->delete($a,$contract)->allowed():false,'synchronize'=>$contract?$p->generateOccurrence($a,$contract)->allowed():false,'generate'=>$contract?$p->generateOccurrence($a,$contract)->allowed():false,'resume'=>$contract?$p->resumeGeneration($a,$contract)->allowed():false,'deleteTerm'=>$contract?$p->delete($a,$contract)->allowed():false]; }
}
