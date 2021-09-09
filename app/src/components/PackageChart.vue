<template>
  <div class="ct-chart ct-minor-seventh">
    <div class="alert alert-danger text-left" role="alert" v-if="errors.length > 0">
      <ul :key="id" class="list-group list-unstyled" v-for="(error, id) in errors">
        <li>{{ error }}</li>
      </ul>
    </div>
    <loading-spinner v-if="loading"></loading-spinner>
  </div>
</template>

<style lang="scss">
  @import "../assets/css/archlinux-bootstrap";
  @import "~bootstrap/scss/functions";
  @import "~bootstrap/scss/variables";

  $ct-series-names: (a, b, c, d, e, f, g, h, i, j);
  $ct-series-colors: (
    $primary,
    $danger,
    $success,
    $warning,
    $info,
    $pink,
    $orange,
    $secondary,
    $purple,
    $gray-500
  );

  @import "~chartist/dist/scss/chartist";

  .ct-legend {
    list-style: none;

    li {
      padding-left: 10px;
      margin-bottom: 5px;
      float: left;
      font-weight: bold;
    }

    &::after {
      content: '';
      display: block;
      clear: left;
    }

    /* stylelint-disable-next-line at-rule-no-unknown */
    @for $i from 0 to length($ct-series-colors) {
      .ct-series-#{$i} {
        color: nth($ct-series-colors, $i + 1);
      }
    }
  }
</style>

<script>
import Chartist from 'chartist'
import 'chartist-plugin-legend'
import LoadingSpinner from './LoadingSpinner'

export default {
  name: 'PackageChart',
  inject: ['apiPackagesService', 'convertToDataSeries'],
  props: {
    packages: {
      type: Array,
      required: true
    },
    startMonth: {
      type: Number,
      required: false
    },
    endMonth: {
      type: Number,
      required: false
    },
    limit: {
      type: Number,
      required: false
    }
  },
  data () {
    return {
      loading: true,
      data: {
        labels: [],
        series: []
      },
      errors: []
    }
  },
  components: {
    LoadingSpinner
  },
  watch: {
    packages: function () {
      this.fetchData()
    },
    data: function () {
      if (this.data.series.length > 0) {
        this.drawChart()
      }
    }
  },
  methods: {
    fetchData () {
      if (this.packages.length < 1) {
        this.loading = false
        this.errors.push('No packages defined')
        return
      }
      if (this.packages.length > 10) {
        this.loading = false
        this.errors.push('Too many packages defined')
        return
      }

      this.loading = true
      Promise.all(this.packages.map(pkg => this.apiPackagesService.fetchPackageSeries(pkg,
        {
          startMonth: this.startMonth,
          endMonth: this.endMonth,
          limit: this.limit
        }).catch(error => { this.errors.push(error) })
      ))
        .then(dataArray => { this.data = this.convertToDataSeries(dataArray) })
        .catch(error => { this.errors.push(error) })
        .finally(() => { this.loading = false })
    },
    drawChart () {
      Chartist.Line(this.$el, this.data, {
        showPoint: false,
        showArea: this.data.series.length < 4,
        chartPadding: {
          top: 24,
          bottom: 12
        },
        axisX: {
          showGrid: false,
          labelInterpolationFnc: value => value.toString().endsWith('01') && value.toString().slice(0, -2) % 2 === 0 ? value.toString().slice(0, -2) : null
        },
        plugins: this.data.series.length > 1 ? [Chartist.plugins.legend({ clickable: false })] : []
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
  mounted () {
    this.fetchData()
  },
  metaInfo () {
    if (this.errors.length > 0 || this.data.series.length < 1) {
      return { meta: [{ vmid: 'robots', name: 'robots', content: 'noindex' }] }
    }
  }
}
</script>
