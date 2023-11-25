<template>
  <div class="container" role="main">
    <h1 class="mb-3">Compare System Architectures</h1>
    <p class="mb-3">Relative usage of system architectures</p>

    <ul class="nav nav-tabs mb-2">
      <li class="nav-item">
        <router-link :to="{name: 'compare-system-architectures', params: {preset: ''}}" class="nav-link" exact>current</router-link>
      </li>
      <li class="nav-item">
        <router-link :to="{name: 'compare-system-architectures', params: {preset: 'all'}}" class="nav-link" exact>all</router-link>
      </li>
      <li class="nav-item">
        <router-link :to="{name: 'compare-system-architectures', params: {preset: 'i686-x86_64'}}" class="nav-link" exact>i686 vs x86_64</router-link>
      </li>
      <li class="nav-item">
        <router-link :to="{name: 'compare-system-architectures', params: {preset: 'x86_64'}}" class="nav-link" exact>x86_64</router-link>
      </li>
      <li class="nav-item">
        <router-link :to="{name: 'compare-system-architectures', params: {preset: 'community'}}" class="nav-link" exact>community supported</router-link>
      </li>
    </ul>

    <system-architecture-chart :key="preset" :limit="0" :systemArchitectures="systemArchitectures" :start-month="startMonth" :end-month="endMonth"></system-architecture-chart>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useHead } from '@vueuse/head'
import { useRouteParams } from '@vueuse/router'
import SystemArchitectureChart from '../components/SystemArchitectureChart'

const preset = useRouteParams('preset')
const systemArchitectures = ref([])
const startMonth = ref(0)
const endMonth = ref(0)

watch(preset, value => {
  switch (value) {
    case 'x86_64':
      systemArchitectures.value = ['x86_64', 'x86_64_v2', 'x86_64_v3', 'x86_64_v4'].sort()
      startMonth.value = 202105
      endMonth.value = 0
      break
    case 'i686-x86_64':
      systemArchitectures.value = ['i686', 'x86_64'].sort()
      startMonth.value = 0
      endMonth.value = 201812
      break
    case 'community':
      systemArchitectures.value = ['i686', 'aarch64', 'armv7', 'armv6', 'armv5', 'riscv64'].sort()
      startMonth.value = 201712
      endMonth.value = 0
      break
    case 'all':
      // @see App\Entity\SystemArchitecture::ARCHITECTURES
      systemArchitectures.value = ['x86_64', 'x86_64_v2', 'x86_64_v3', 'x86_64_v4', 'i686', 'aarch64', 'armv7', 'armv6', 'armv5', 'riscv64'].sort()
      startMonth.value = 0
      endMonth.value = 0
      break
    default:
      systemArchitectures.value = ['x86_64', 'x86_64_v2', 'x86_64_v3', 'x86_64_v4', 'i686', 'aarch64', 'armv7', 'armv6', 'armv5', 'riscv64'].sort()
      startMonth.value = 202105
      endMonth.value = 0
  }
}, { immediate: true })

useHead({ title: 'Compare System Architectures' })
</script>
