<template>
  <main class="container" role="main">
    <h1 class="mb-4">Privacy policy</h1>
    <div class="row">
      <div class="col-12 col-lg-6">
        <h2>Server logs</h2>
        <p>Requests to this website are logged. The following data are stored:</p>
        <ul>
          <li>IP address</li>
          <li>Date and time of the request</li>
          <li>Actual Request of the client</li>
          <li>Response code of the server</li>
          <li>Size of the transferred data</li>
          <li>Referrer sent by the client</li>
          <li>User agent sent by the client</li>
        </ul>
        <p>These information are used to generate anonymous statistics and to prevent attacks against this
          website.
          The log entries are stored for one month.</p>
      </div>
      <div class="col-12 col-lg-6 ps-lg-5">
        <h2>pkgstats command line tool</h2>
        <p>The data send by the pkgstats command line tool is stored anonymously on the server. See
          <code>pkgstats -s</code> for a list of the data to be transferred.</p>
        <p>The client IP is used for a local GeoIP lookup, which means the IP is not send to any third party.
          The
          resulting country code is stored in a database alongside an anonymized IP hash. This data is used to
          create statistics found on this site and to block abusive submissions.</p>
        <p>No personal data is persisted.</p>
        <h2>Contact</h2>
        <p>See the
          <router-link :to="{name: 'impressum'}">Impressum</router-link>
          for ways to contact us.
        </p>
      </div>
    </div>
  </main>
</template>

<script setup>
import { useHead } from '@vueuse/head'

useHead({
  title: 'Privacy policy',
  meta: [{ name: 'robots', content: 'noindex' }]
})
</script>
