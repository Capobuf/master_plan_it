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
use App\Domain\Expenses\Enums\ActualConfirmationState;
use App\Domain\Contracts\Queries\ContractDetailQuery;
use App\Domain\Contracts\Queries\ContractListQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractTerm;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\RevisionBatch;
use App\Policies\ContractPolicy;
use App\Support\Formatting\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * @phpstan-type ContractListProps array{id:int, title:string, vendorName:string|null, costCenterName:string|null, active:bool, effectiveStart:string|null, effectiveEnd:string|null, termCount:int, generatedExpenseCount:int, lockVersion:int}
 * @phpstan-type ContractTermProps array{id:int, localKey:string, effectiveStart:string, effectiveEnd:string, billingCycle:string, quantity:string|null, unitPrice:string|null, enteredAmount:string, amountIncludesVat:bool, vatRate:string, net:string, vat:string, gross:string, autoRenew:bool, lockVersion:int}
 * @phpstan-type ContractDetailProps array{id:int, title:string, vendorName:string|null, costCenterName:string|null, active:bool, effectiveStart:string|null, effectiveEnd:string|null, termCount:int, generatedExpenseCount:int, lockVersion:int, vendorId:int, costCenterId:int, description:string|null, renewalDate:string|null, renewalNoticeDays:int|null, renewalNotes:string|null, terms:list<ContractTermProps>}
 * @phpstan-type GeneratedExpenseProps array{id:int, title:string, href:string, state:string|null, confirmationState:string|null, isSystemManaged:bool, sourceKey:string, occurrenceDate:string|null, periodLabel:string, gross:string}
 * @phpstan-type RevisionActivityProps array{id:int, operation:string, actor:string|null, timestamp:string, summary:string|null}
 */
final class ContractController extends Controller
{
    public function index(Request $request, ContractListQuery $query): View
    {
        $items = $query->paginate($this->actor($request), $this->tenantContext($request), (int)$request->query('page',1));
        return view('operational.contracts.index', ['contracts'=>['data'=>collect($items->items())->map(fn(Contract $c)=>$this->listProps($c))->all(),'currentPage'=>$items->currentPage(),'lastPage'=>$items->lastPage(),'perPage'=>$items->perPage(),'total'=>$items->total(),'from'=>$items->firstItem(),'to'=>$items->lastItem(),'links'=>$items->linkCollection()->all()],'abilities'=>$this->abilities($request)]);
    }

    public function create(Request $request): View
    {
        app(ContractPolicy::class)->create($this->actor($request))->authorize();
        return view('operational.contracts.create', $this->formProps($this->tenantContext($request), null));
    }

    public function store(Request $request, CreateContract $action): RedirectResponse
    {
        $contract = $action->execute($this->actor($request), $this->tenantContext($request), $this->data($request, false), $this->correlationId($request));
        return redirect()->route('operational.contracts.show',$contract)->with('success','Contract created.');
    }

    public function show(Request $request, int $contract, ContractDetailQuery $query): View
    {
        $context=$this->tenantContext($request); $detail=$query->find($this->actor($request),$context,$contract); $target=$detail['contract'];
        $generated=$target->expenses->filter(static fn (Expense $expense): bool => $expense->rows->contains(static fn (Model $row): bool => $row instanceof ExpenseRow && $row->source_key !== null))->map(function (Expense $expense) use ($context): array {$row=$expense->rows->first(static fn (Model $candidate): bool => $candidate instanceof ExpenseRow && $candidate->source_key !== null); return $this->generatedExpenseProps($expense, $row instanceof ExpenseRow ? $row : null, $context->currencyCode);})->values()->all();
        $suppressed=collect($detail['occurrences'])->filter(fn($o)=>$o->suppressed)->map(fn($o)=>['sourceKey'=>$o->sourceKey,'occurrenceDate'=>$o->occurrenceDate,'termId'=>$o->termId])->values()->all();
        $revisionActivity=app(ContractPolicy::class)->viewRevisions($this->actor($request),$target)->allowed()?RevisionBatch::query()->where('tenant_id',$context->tenantId)->where('root_subject_type',$target->getMorphClass())->where('root_subject_id',$target->id)->with('actor')->orderByDesc('occurred_at')->limit(10)->get()->map(fn (RevisionBatch $batch): array => $this->revisionActivityProps($batch))->all():[];
        $contractProps=$this->detailProps($target,$context->currencyCode)+['generatedExpenses'=>$generated,'suppressedOccurrences'=>$suppressed,'revisionActivity'=>$revisionActivity];
        return view('operational.contracts.show',['contract'=>$contractProps,'generatedExpenses'=>$generated,'suppressedOccurrences'=>$suppressed,'revisionActivity'=>$revisionActivity,'planningYears'=>\App\Models\PlanningYear::query()->where('tenant_id',$context->tenantId)->orderBy('year_label')->get()->map(fn($y)=>['value'=>(int)$y->year_label,'label'=>(string)$y->year_label])->all(),'deletionReasonRequired'=>(bool)$context->tenant->deletion_reason_required,'abilities'=>$this->abilities($request,$target)]);
    }

    public function edit(Request $request,int $contract): View
    {
        $context=$this->tenantContext($request); $target=$this->contract($context,$contract)->load('terms'); app(ContractPolicy::class)->update($this->actor($request),$target)->authorize();
        return view('operational.contracts.edit',$this->formProps($context,$target));
    }

    public function update(Request $request,int $contract,UpdateContract $action): RedirectResponse
    {
        $context=$this->tenantContext($request); $action->execute($this->actor($request),$context,$this->contract($context,$contract),$this->data($request,true),$this->correlationId($request));
        return redirect()->route('operational.contracts.show',$contract)->with('success','Contract updated.');
    }

    public function destroy(Request $request,int $contract,DeleteContract $action): RedirectResponse
    {
        $v=$request->validate(['lock_version'=>['required','integer','min:1'],'deletion_reason'=>['nullable','string','max:500']]); $context=$this->tenantContext($request);
        $action->execute($this->actor($request),$context,$this->contract($context,$contract),(int)$v['lock_version'],$v['deletion_reason']??null,$this->correlationId($request));
        return redirect()->route('operational.contracts.index')->with('success','Contract deleted.');
    }

    public function synchronize(Request $request,int $contract,SynchronizeContractOccurrences $action): RedirectResponse { $context=$this->tenantContext($request); $result=$action->execute($this->actor($request),$context,$this->contract($context,$contract),$this->correlationId($request)); return back()->with('success',"Synchronization complete: {$result['created']} created, {$result['updated']} updated."); }
    public function generate(Request $request,int $contract,int $year,GenerateContractOccurrenceForYear $action): RedirectResponse { $context=$this->tenantContext($request); $action->execute($this->actor($request),$context,$this->contract($context,$contract),$year,$this->correlationId($request)); return back()->with('success','Occurrence generated.'); }
    public function resume(Request $request,int $contract,string $sourceKey,ResumeContractOccurrence $action): RedirectResponse { $context=$this->tenantContext($request); $action->execute($this->actor($request),$context,$this->contract($context,$contract),$sourceKey,$this->correlationId($request)); return back()->with('success','Occurrence resumed.'); }
    public function resumeAndGenerate(Request $request,int $contract,string $sourceKey,ResumeAndGenerateOccurrence $action): RedirectResponse { $context=$this->tenantContext($request); $action->execute($this->actor($request),$context,$this->contract($context,$contract),$sourceKey,$this->correlationId($request)); return back()->with('success','Occurrence resumed and generated.'); }
    public function destroyTerm(Request $request,int $contract,int $term,DeleteContractTerm $action): RedirectResponse { $v=$request->validate(['lock_version'=>['required','integer','min:1'],'deletion_reason'=>['nullable','string','max:500']]);$context=$this->tenantContext($request);$target=$this->contract($context,$contract);$targetTerm=ContractTerm::query()->where('tenant_id',$context->tenantId)->where('contract_id',$target->id)->findOrFail($term);$action->execute($this->actor($request),$context,$target,$targetTerm,(int)$v['lock_version'],$v['deletion_reason']??null,$this->correlationId($request));return back()->with('success','Contract term deleted.'); }

    private function data(Request $request,bool $update): SaveContractData
    {
        $rules=['vendor_id'=>['required','integer'],'cost_center_id'=>['required','integer'],'title'=>['required','string','max:255'],'description'=>['nullable','string'],'active'=>['required','boolean'],'renewal_date'=>['nullable','date_format:Y-m-d'],'renewal_notice_days'=>['nullable','integer','min:0'],'renewal_notes'=>['nullable','string'],'terms'=>['required','array','min:1'],'terms.*.id'=>['nullable','integer'],'terms.*.local_key'=>['required','string','max:100'],'terms.*.effective_start'=>['required','date_format:Y-m-d'],'terms.*.effective_end'=>['required','date_format:Y-m-d'],'terms.*.billing_cycle'=>['required',Rule::enum(BillingCycle::class)],'terms.*.quantity'=>['nullable','string'],'terms.*.unit_price'=>['nullable','string'],'terms.*.entered_amount'=>['required','string'],'terms.*.amount_includes_vat'=>['required','boolean'],'terms.*.vat_rate'=>['nullable','string'],'terms.*.auto_renew'=>['required','boolean'],'terms.*.lock_version'=>['nullable','integer','min:1']];if($update){$rules['lock_version']=['required','integer','min:1'];}$v=$request->validate($rules);
        $terms=array_map(fn($t)=>new SaveContractTermData(isset($t['id'])?(int)$t['id']:null,$t['local_key'],$t['effective_start'],$t['effective_end'],BillingCycle::from($t['billing_cycle']),$t['quantity']??null,$t['unit_price']??null,$t['entered_amount'],(bool)$t['amount_includes_vat'],$t['vat_rate']??'',(bool)$t['auto_renew'],isset($t['lock_version'])?(int)$t['lock_version']:null),$v['terms']);
        return new SaveContractData((int)$v['vendor_id'],(int)$v['cost_center_id'],$v['title'],$v['description']??null,(bool)$v['active'],$v['renewal_date']??null,isset($v['renewal_notice_days'])?(int)$v['renewal_notice_days']:null,$v['renewal_notes']??null,isset($v['lock_version'])?(int)$v['lock_version']:null,$terms);
    }

    private function contract(TenantContext $context,int $id): Contract { return Contract::query()->where('tenant_id',$context->tenantId)->findOrFail($id); }
    /** @return ContractListProps */
    private function listProps(Contract $c): array { return ['id'=>(int)$c->id,'title'=>$c->title,'vendorName'=>$c->vendor?->name,'costCenterName'=>$c->costCenter?->name,'active'=>(bool)$c->active,'effectiveStart'=>$c->terms->min(fn(ContractTerm $term): string => $term->effective_start->toDateString()),'effectiveEnd'=>$c->terms->max(fn(ContractTerm $term): string => $term->effective_end->toDateString()),'termCount'=>(int)$c->terms_count,'generatedExpenseCount'=>(int)($c->generated_expenses_count??0),'lockVersion'=>(int)$c->lock_version]; }
    /** @return ContractDetailProps */
    private function detailProps(Contract $c,string $currency): array { return $this->listProps($c)+['vendorId'=>(int)$c->vendor_id,'costCenterId'=>(int)$c->cost_center_id,'description'=>$c->description,'renewalDate'=>$c->renewal_date?->toDateString(),'renewalNoticeDays'=>$c->renewal_notice_days,'renewalNotes'=>$c->renewal_notes,'lockVersion'=>(int)$c->lock_version,'terms'=>$c->terms->map(fn(ContractTerm $term): array => $this->contractTermProps($term, $currency))->all()]; }
    /** @return array{contract:ContractDetailProps|null, vendors:mixed, costCenters:mixed, defaults:array{vatRate:string}} */
    private function formProps(TenantContext $context,?Contract $contract): array { $options=fn($q)=>$q->where('tenant_id',$context->tenantId)->orderBy('name')->get(['id','name'])->map(fn($x)=>['value'=>(int)$x->id,'label'=>$x->name])->all();return ['contract'=>$contract===null?null:$this->detailProps($contract,$context->currencyCode),'vendors'=>$options(\App\Models\Vendor::query()),'costCenters'=>$options(\App\Models\CostCenter::query()),'defaults'=>['vatRate'=>$context->defaultVatRate]]; }
    /** @return array{create:bool, update:bool, delete:bool, synchronize:bool, generate:bool, resume:bool, deleteTerm:bool} */
    private function abilities(Request $request,?Contract $contract=null): array { $p=app(ContractPolicy::class);$a=$this->actor($request);return ['create'=>$p->create($a)->allowed(),'update'=>$contract?$p->update($a,$contract)->allowed():false,'delete'=>$contract?$p->delete($a,$contract)->allowed():false,'synchronize'=>$contract?$p->generateOccurrence($a,$contract)->allowed():false,'generate'=>$contract?$p->generateOccurrence($a,$contract)->allowed():false,'resume'=>$contract?$p->resumeGeneration($a,$contract)->allowed():false,'deleteTerm'=>$contract?$p->update($a,$contract)->allowed():false]; }

    /** @return ContractTermProps */
    private function contractTermProps(ContractTerm $term, string $currency): array { return ['id'=>(int)$term->id,'localKey'=>'term-'.$term->id,'effectiveStart'=>$term->effective_start->toDateString(),'effectiveEnd'=>$term->effective_end->toDateString(),'billingCycle'=>$term->billing_cycle->value,'quantity'=>$term->quantity,'unitPrice'=>$term->unit_price,'enteredAmount'=>$term->entered_amount,'amountIncludesVat'=>$term->amount_includes_vat,'vatRate'=>$term->vat_rate,'net'=>MoneyFormatter::format($term->net_amount,$currency),'vat'=>MoneyFormatter::format($term->vat_amount,$currency),'gross'=>MoneyFormatter::format($term->gross_amount,$currency),'autoRenew'=>$term->auto_renew,'lockVersion'=>$term->lock_version]; }

    /** @return GeneratedExpenseProps */
    private function generatedExpenseProps(Expense $expense, ?ExpenseRow $row, string $currency): array
    {
        $state=$row?->getAttribute('confirmation_state');
        $occurrenceDate=$row?->getAttribute('contract_occurrence_date');
        $occurrenceDateValue=$occurrenceDate instanceof \DateTimeInterface ? $occurrenceDate->format('Y-m-d') : $row?->spend_date;
        $periodLabel=$occurrenceDate instanceof \DateTimeInterface ? $occurrenceDate->format('m/Y') : substr((string) $row?->spend_date,0,7);
        return ['id'=>(int)$expense->id,'title'=>$expense->title,'href'=>route('operational.expenses.show',$expense),'state'=>$state instanceof ActualConfirmationState ? $state->value : null,'confirmationState'=>$state instanceof ActualConfirmationState ? $state->value : null,'isSystemManaged'=>(bool)$row?->is_system_managed,'sourceKey'=>(string)$row?->source_key,'occurrenceDate'=>$occurrenceDateValue,'periodLabel'=>$periodLabel,'gross'=>MoneyFormatter::format((string)$row?->gross_amount,$currency)];
    }

    /** @return RevisionActivityProps */
    private function revisionActivityProps(RevisionBatch $batch): array { return ['id'=>(int)$batch->id,'operation'=>$batch->operation->value,'actor'=>$batch->actor?->name,'timestamp'=>$batch->occurred_at->toIso8601String(),'summary'=>$batch->reason]; }
}
