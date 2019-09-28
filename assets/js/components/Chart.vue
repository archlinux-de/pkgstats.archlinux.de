<template>
  <div class="ct-chart ct-minor-seventh">
    <slot v-if="loading"></slot>
  </div>
</template>

<script>
    import Chartist from 'chartist'
    import 'chartist-plugin-legend'
    // support IE 11
    import 'whatwg-fetch'
    import {convertToDataSeries} from "../services/DataSeriesConverter";

    export default {
        name: 'Chart',
        data() {
            return {
                loading: true,
                data: [],
                urls: this.$parent.$data.urls
            }
        },
        watch: {
            urls: function () {
                this.fetchData()
            },
            data: function () {
                this.drawChart()
            }
        },
        methods: {
            fetchData: function () {
                this.loading = true
                Promise.all(this.urls.map(url => {
                    return fetch(url, {
                        credentials: 'omit',
                        headers: new Headers({Accept: 'application/json'})
                    }).then(response => response.json())
                }))
                    .then(dataArray => {
                        dataArray.forEach(data => {
                            if (!data.count) {
                                throw new Error('No package data found')
                            }
                        })
                        this.data = convertToDataSeries(dataArray)
                        this.loading = false
                    })
                    .catch(e => {
                        this.loading = false
                        const error = document.createElement('div')
                        error.classList.add('alert')
                        error.classList.add('alert-danger')
                        error.setAttribute('role', 'alert')
                        error.innerText = e.toString()
                        this.$el.appendChild(error)
                    })
            },
            drawChart: function () {
                Chartist.Line(this.$el, this.data, {
                    showPoint: false,
                    showArea: this.data.series.length < 4,
                    chartPadding: {
                        top: 24
                    },
                    axisX: {
                        showGrid: false,
                        labelInterpolationFnc: value => value.toString().endsWith('01') && value.toString().slice(0, -2) % 2 === 0 ? value.toString().slice(0, -2) : null
                    },
                    plugins: this.data.series.length > 1 ? [
                        Chartist.plugins.legend({
                            clickable: false
                        })
                    ] : []
                }, [
                    ['screen and (min-width: 576px)', {
                        chartPadding: {
                            top: 36
                        },
                        axisX: {
                            labelInterpolationFnc: value => value.toString().endsWith('01') ? value.toString().slice(0, -2) : null
                        }
                    }],
                    ['screen and (min-width: 768px)', {
                        chartPadding: {
                            top: 48
                        }
                    }]
                ])
            }
        },
        mounted() {
            this.fetchData()
        }
    }
</script>
