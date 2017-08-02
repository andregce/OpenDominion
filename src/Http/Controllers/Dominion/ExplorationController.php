<?php

namespace OpenDominion\Http\Controllers\Dominion;

use Exception;
use OpenDominion\Contracts\Calculators\Dominion\Actions\ExplorationCalculator;
use OpenDominion\Contracts\Calculators\Dominion\LandCalculator;
use OpenDominion\Contracts\Services\AnalyticsService;
use OpenDominion\Contracts\Services\Dominion\Actions\ExploreActionService;
use OpenDominion\Exceptions\BadInputException;
use OpenDominion\Exceptions\DominionLockedException;
use OpenDominion\Exceptions\NotEnoughResourcesException;
use OpenDominion\Helpers\LandHelper;
use OpenDominion\Http\Requests\Dominion\Actions\ExploreActionRequest;
use OpenDominion\Services\AnalyticsService\Event;
use OpenDominion\Services\Dominion\QueueService;

class ExplorationController extends AbstractDominionController
{
    public function getExplore()
    {
        return view('pages.dominion.explore', [
            'dominionQueueService' => app(QueueService::class),
            'explorationCalculator' => app(ExplorationCalculator::class),
            'landCalculator' => app(LandCalculator::class),
            'landHelper' => app(LandHelper::class),
        ]);
    }

    public function postExplore(ExploreActionRequest $request)
    {
        $dominion = $this->getSelectedDominion();
        $explorationActionService = app(ExploreActionService::class);

        try {
            $result = $explorationActionService->explore($dominion, $request->get('explore'));

        } catch (Exception $e) {
            return redirect()->back()
                ->withInput($request->all())
                ->withErrors([$e->getMessage()]);
        }

        $message = sprintf(
            'Exploration begun at a cost of %s platinum and %s draftees. Your orders for exploration disheartens the military, and morale drops %s%%.',
            number_format($result['platinumCost']),
            number_format($result['drafteeCost']),
            number_format($result['moraleDrop'])
        );

        // todo: fire laravel event
        $analyticsService = app(AnalyticsService::class);
        $analyticsService->queueFlashEvent(new Event(
            'dominion',
            'explore',
            '', // todo: make null?
            array_sum($request->get('explore'))
        ));

        $request->session()->flash('alert-success', $message);
        return redirect()->route('dominion.explore');
    }
}
