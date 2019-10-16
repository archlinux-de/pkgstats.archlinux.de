<template functional>
  <div id="spinner">
    <div class="spinner-border text-primary" role="status">
      <span class="sr-only">Loading...</span>
    </div>
  </div>
</template>

<style lang="scss" scoped>
  #spinner {
    position: absolute;
    left: 50%;
    transform: translate(-50%, 150%);
    opacity: 0;
    animation: delay-opacity ease-in 1.2s forwards;
    animation-delay: 0.2s;
  }

  @keyframes delay-opacity {
    to {
      opacity: 1;
    }
  }
</style>
