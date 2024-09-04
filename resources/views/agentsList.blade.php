<x-app-layout>
    <!-- Main Content of the page -->
    <div class="mb-6 grid gap-6 xl:grid-cols-3">

        <!-- 1ft column -->
        <div class="h-full xl:col-span-1 border border-gray-200 rounded-lg">

            <div class="mb-5 p-2">
                <h5 class="text-lg font-semibold dark:text-white-light">See & Do More</h5>
                <h3 class="text-base font-medium dark:text-white-light mt-2">
                    Total users: <span class="text-lg text-[#f8538d]">4230964</span>
                </h3>
               
                <div x-ref="totalVisit" class="overflow-hidden w-full">
    <div id="apexchartsaoiaeoag" class="w-full apexcharts-canvas apexchartsaoiaeoag apexcharts-theme-light"
        style="width: 100%; height: 58px;">
        <svg id="SvgjsSvg1511" width="100%" height="58" xmlns="http://www.w3.org/2000/svg" version="1.1"
            xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:svgjs="http://svgjs.dev" class="apexcharts-svg"
            xmlns:data="ApexChartsNS" transform="translate(0, 0)" style="background: transparent; width: 100%;">
            <g id="SvgjsG1513" class="apexcharts-inner apexcharts-graphical"
                transform="translate(0, 5)">
                <defs id="SvgjsDefs1512">
                    <clipPath id="gridRectMaskaoiaeoag">
                        <rect id="SvgjsRect1518" width="100%" height="50" x="0" y="-1" rx="0" ry="0"
                            opacity="1" stroke-width="0" stroke="none" stroke-dasharray="0" fill="#fff">
                        </rect>
                    </clipPath>
                    <!-- Other clip paths and filters remain the same -->
                </defs>
                <!-- Line and grid adjustments to ensure full width -->
                <line id="SvgjsLine1517" x1="0" y1="0" x2="100%" y2="48" stroke="#b6b6b6"
                    stroke-dasharray="3" stroke-linecap="butt" class="apexcharts-xcrosshairs" x="0"
                    y="0" width="100%" height="48" fill="#b1b9c4" filter="none" fill-opacity="0.9"
                    stroke-width="1"></line>
                <g id="SvgjsG1534" class="apexcharts-xaxis" transform="translate(0, 0)">
                    <g id="SvgjsG1535" class="apexcharts-xaxis-texts-g" transform="translate(0, -4)">
                    </g>
                </g>
                <g id="SvgjsG1547" class="apexcharts-grid">
                    <g id="SvgjsG1548" class="apexcharts-gridlines-horizontal" style="display: none;">
                        <line id="SvgjsLine1550" x1="0" y1="0" x2="100%" y2="0" stroke="#e0e0e0"
                            stroke-dasharray="0" stroke-linecap="butt" class="apexcharts-gridline">
                        </line>
                        <!-- Repeat this pattern for other gridlines -->
                    </g>
                    <line id="SvgjsLine1559" x1="0" y1="48" x2="100%" y2="48" stroke="transparent"
                        stroke-dasharray="0" stroke-linecap="butt"></line>
                </g>
                <g id="SvgjsG1520" class="apexcharts-line-series apexcharts-plot-series">
                    <g id="SvgjsG1521" class="apexcharts-series" seriesName="seriesx1"
                        data:longestSeries="true" rel="1" data:realIndex="0">
                        <path id="SvgjsPath1524"
                            d="M 0 33.6C 7.6611111111111105 33.6 14.227777777777778 41.82857142857143 21.88888888888889 41.82857142857143C 29.55 41.82857142857143 36.11666666666667 23.314285714285713 43.77777777777778 23.314285714285713C 51.43888888888889 23.314285714285713 58.00555555555556 39.77142857142857 65.66666666666667 39.77142857142857C 73.32777777777778 39.77142857142857 79.89444444444445 17.828571428571426 87.55555555555556 17.828571428571426C 95.21666666666667 17.828571428571426 101.78333333333333 30.857142857142858 109.44444444444444 30.857142857142858C 117.10555555555555 30.857142857142858 123.67222222222223 7.542857142857137 131.33333333333334 7.542857142857137C 138.99444444444444 7.542857142857137 145.56111111111113 19.885714285714283 153.22222222222223 19.885714285714283C 160.88333333333333 19.885714285714283 167.45000000000002 2.74285714285714 175.11111111111111 2.74285714285714C 182.7722222222222 2.74285714285714 189.3388888888889 30.857142857142858 197 30.857142857142858"
                            fill="none" fill-opacity="1" stroke="rgba(0,150,136,0.85)"
                            stroke-opacity="1" stroke-linecap="butt" stroke-width="2"
                            stroke-dasharray="0" class="apexcharts-line" index="0"
                            clip-path="url(#gridRectMaskaoiaeoag)" filter="url(#SvgjsFilter1525)"
                            pathTo="M 0 33.6C 7.6611111111111105 33.6 14.227777777777778 41.82857142857143 21.88888888888889 41.82857142857143C 29.55 41.82857142857143 36.11666666666667 23.314285714285713 43.77777777777778 23.314285714285713C 51.43888888888889 23.314285714285713 58.00555555555556 39.77142857142857 65.66666666666667 39.77142857142857C 73.32777777777778 39.77142857142857 79.89444444444445 17.828571428571426 87.55555555555556 17.828571428571426C 95.21666666666667 17.828571428571426 101.78333333333333 30.857142857142858 109.44444444444444 30.857142857142858C 117.10555555555555 30.857142857142858 123.67222222222223 7.542857142857137 131.33333333333334 7.542857142857137C 138.99444444444444 7.542857142857137 145.56111111111113 19.885714285714283 153.22222222222223 19.885714285714283C 160.88333333333333 19.885714285714283 167.45000000000002 2.74285714285714 175.11111111111111 2.74285714285714C 182.7722222222222 2.74285714285714 189.3388888888889 30.857142857142858 197 30.857142857142858"
                            pathFrom="M -1 48L -1 48L 21.88888888888889 48L 43.77777777777778 48L 65.66666666666667 48L 87.55555555555556 48L 109.44444444444444 48L 131.33333333333334 48L 153.22222222222223 48L 175.11111111111111 48L 197 48">
                        </path>
                        <g id="SvgjsG1522" class="apexcharts-series-markers-wrap" data:realIndex="0">
                            <g class="apexcharts-series-markers">
                                <circle id="SvgjsCircle1565" r="0" cx="0" cy="0"
                                    class="apexcharts-marker wype4wn6a no-pointer-events"
                                    stroke="#ffffff" fill="#009688" fill-opacity="1" stroke-width="2"
                                    stroke-opacity="0.9" default-marker-size="0">
                                </circle>
                            </g>
                        </g>
                    </g>
                    <g id="SvgjsG1523" class="apexcharts-datalabels" data:realIndex="0"></g>
                </g>
                <line id="SvgjsLine1560" x1="0" y1="0" x2="100%" y2="0" stroke="#b6b6b6"
                    stroke-dasharray="0" stroke-width="1" stroke-linecap="butt"
                    class="apexcharts-ycrosshairs"></line>
                <line id="SvgjsLine1561" x1="0" y1="0" x2="100%" y2="0" stroke-dasharray="0"
                    stroke-width="0" stroke-linecap="butt" class="apexcharts-ycrosshairs-hidden">
                </line>
                <g id="SvgjsG1562" class="apexcharts-yaxis-annotations"></g>
                <g id="SvgjsG1563" class="apexcharts-xaxis-annotations"></g>
                <g id="SvgjsG1564" class="apexcharts-point-annotations"></g>
            </g>
            <rect id="SvgjsRect1516" width="0" height="0" x="0" y="0" rx="0" ry="0" opacity="1"
                stroke-width="0" stroke="none" stroke-dasharray="0" fill="#fefefe"></rect>
            <g id="SvgjsG1546" class="apexcharts-yaxis" rel="0" transform="translate(-18, 0)"></g>
            <g id="SvgjsG1514" class="apexcharts-annotations"></g>
        </svg>
        <div class="apexcharts-legend" style="max-height: 29px;"></div>
        <div class="apexcharts-tooltip apexcharts-theme-light">
            <div class="apexcharts-tooltip-series-group" style="order: 1;"><span
                    class="apexcharts-tooltip-marker"
                    style="background-color: rgb(0, 150, 136);"></span>
                <div class="apexcharts-tooltip-text"
                    style="font-family: Nunito, sans-serif; font-size: 12px;">
                    <div class="apexcharts-tooltip-y-group"><span
                            class="apexcharts-tooltip-text-y-label"></span><span
                            class="apexcharts-tooltip-text-y-value"></span></div>
                    <div class="apexcharts-tooltip-goals-group"><span
                            class="apexcharts-tooltip-text-goals-label"></span><span
                            class="apexcharts-tooltip-text-goals-value"></span></div>
                    <div class="apexcharts-tooltip-z-group"><span
                            class="apexcharts-tooltip-text-z-label"></span><span
                            class="apexcharts-tooltip-text-z-value"></span></div>
                </div>
            </div>
        </div>
        <div
            class="apexcharts-yaxistooltip apexcharts-yaxistooltip-0 apexcharts-yaxistooltip-left apexcharts-theme-light">
            <div class="apexcharts-yaxistooltip-text"></div>
        </div>
    </div>
</div>


        </div>

        <!-- Add more here -->
    </div>

    <!--  2nd columns -->
    <div class="panel h-full xl:col-span-2">
        <div class="mb-5 flex items-center dark:text-white-light">
            <h5 class="text-lg font-semibold">Agents List</h5>
        </div>
        <div
            class="apexcharts-yaxistooltip apexcharts-yaxistooltip-0 apexcharts-yaxistooltip-left apexcharts-theme-light">
            <div class="apexcharts-yaxistooltip-text"></div>
        </div>
    </div>
   
</x-app-layout>