import { useEffect, useRef, useState } from 'react'
import { useFrame, useThree } from '@react-three/fiber'
import { MathUtils, Vector3 } from 'three'

const MOVEMENT_KEYS = new Set([
  'KeyW', 'KeyA', 'KeyS', 'KeyD',
  'ArrowUp', 'ArrowLeft', 'ArrowDown', 'ArrowRight',
])

export function useFirstPersonNavigationInput() {
  return useRef({
    moveX: 0,
    moveY: 0,
    lookX: 0,
    lookY: 0,
    mouseX: 0,
    mouseY: 0,
    ladder: null,
    climbDirection: 0,
    resetRequested: false,
  })
}

export function FirstPersonController({
  inputRef,
  initialPosition,
  bounds,
  eyeHeight = 1.65,
  speed = 2.7,
}) {
  const { camera, gl, pointer } = useThree()
  const keysRef = useRef(new Set())
  const yawRef = useRef(Math.PI)
  const pitchRef = useRef(0)
  const forward = useRef(new Vector3())
  const right = useRef(new Vector3())
  const lastInteractionView = useRef({ x: NaN, y: NaN, z: NaN, yaw: NaN, pitch: NaN })

  const resetCamera = () => {
    camera.position.set(initialPosition[0], eyeHeight, initialPosition[2])
    yawRef.current = Math.PI
    pitchRef.current = 0
    camera.rotation.order = 'YXZ'
    camera.rotation.set(0, Math.PI, 0)
    inputRef.current.ladder = null
    inputRef.current.climbDirection = 0
  }

  useEffect(() => {
    resetCamera()

    const canvas = gl.domElement
    const isFormTarget = (target) => (
      target instanceof HTMLElement
      && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))
    )
    const handleKeyDown = (event) => {
      if (isFormTarget(event.target) || !MOVEMENT_KEYS.has(event.code)) return
      event.preventDefault()
      keysRef.current.add(event.code)
    }
    const handleKeyUp = (event) => {
      if (!MOVEMENT_KEYS.has(event.code)) return
      event.preventDefault()
      keysRef.current.delete(event.code)
    }
    const clearKeys = () => keysRef.current.clear()
    const handleCanvasPointerDown = (event) => {
      if (event.pointerType !== 'mouse' || event.button !== 0 || document.pointerLockElement === canvas) return
      canvas.requestPointerLock?.()
    }
    const handleMouseMove = (event) => {
      if (document.pointerLockElement !== canvas) return
      inputRef.current.mouseX += event.movementX
      inputRef.current.mouseY += event.movementY
    }
    const handlePointerLockChange = () => {
      if (document.pointerLockElement === canvas) pointer.set(0, 0)
    }

    window.addEventListener('keydown', handleKeyDown, { passive: false })
    window.addEventListener('keyup', handleKeyUp, { passive: false })
    window.addEventListener('blur', clearKeys)
    canvas.addEventListener('pointerdown', handleCanvasPointerDown)
    document.addEventListener('mousemove', handleMouseMove)
    document.addEventListener('pointerlockchange', handlePointerLockChange)

    return () => {
      window.removeEventListener('keydown', handleKeyDown)
      window.removeEventListener('keyup', handleKeyUp)
      window.removeEventListener('blur', clearKeys)
      canvas.removeEventListener('pointerdown', handleCanvasPointerDown)
      document.removeEventListener('mousemove', handleMouseMove)
      document.removeEventListener('pointerlockchange', handlePointerLockChange)
      if (document.pointerLockElement === canvas) document.exitPointerLock?.()
    }
  }, [camera, eyeHeight, gl, initialPosition[0], initialPosition[2], inputRef, pointer])

  useFrame((state, delta) => {
    const input = inputRef.current
    if (input.resetRequested) {
      input.resetRequested = false
      resetCamera()
    }

    yawRef.current -= input.mouseX * 0.0021 + input.lookX * delta * 1.75
    pitchRef.current -= input.mouseY * 0.0018 + input.lookY * delta * 1.45
    pitchRef.current = MathUtils.clamp(pitchRef.current, -1.28, 1.28)
    input.mouseX = 0
    input.mouseY = 0

    camera.rotation.order = 'YXZ'
    camera.rotation.y = yawRef.current
    camera.rotation.x = pitchRef.current

    const refreshCenteredInteraction = () => {
      if (typeof document === 'undefined' || document.pointerLockElement !== gl.domElement) return

      const previous = lastInteractionView.current
      const viewChanged = Math.abs(previous.x - camera.position.x) > 0.001
        || Math.abs(previous.y - camera.position.y) > 0.001
        || Math.abs(previous.z - camera.position.z) > 0.001
        || Math.abs(previous.yaw - yawRef.current) > 0.0005
        || Math.abs(previous.pitch - pitchRef.current) > 0.0005

      if (!viewChanged) return
      previous.x = camera.position.x
      previous.y = camera.position.y
      previous.z = camera.position.z
      previous.yaw = yawRef.current
      previous.pitch = pitchRef.current
      state.events.update?.()
    }

    const keys = keysRef.current
    if (input.ladder?.active) {
      const keyboardClimb = (keys.has('KeyW') || keys.has('ArrowUp') ? 1 : 0)
        - (keys.has('KeyS') || keys.has('ArrowDown') ? 1 : 0)
      const climbDirection = MathUtils.clamp(
        input.climbDirection + input.moveY + keyboardClimb,
        -1,
        1,
      )
      camera.position.x = MathUtils.damp(camera.position.x, input.ladder.x, 8, delta)
      camera.position.z = MathUtils.damp(camera.position.z, input.ladder.z, 8, delta)
      camera.position.y = MathUtils.clamp(
        camera.position.y + climbDirection * speed * 0.72 * delta,
        eyeHeight,
        input.ladder.maxY,
      )
      refreshCenteredInteraction()
      return
    }

    let moveForward = input.moveY
      + (keys.has('KeyW') || keys.has('ArrowUp') ? 1 : 0)
      - (keys.has('KeyS') || keys.has('ArrowDown') ? 1 : 0)
    let moveRight = input.moveX
      + (keys.has('KeyD') || keys.has('ArrowRight') ? 1 : 0)
      - (keys.has('KeyA') || keys.has('ArrowLeft') ? 1 : 0)
    const magnitude = Math.hypot(moveForward, moveRight)
    if (magnitude > 1) {
      moveForward /= magnitude
      moveRight /= magnitude
    }

    forward.current.set(-Math.sin(yawRef.current), 0, -Math.cos(yawRef.current))
    right.current.set(Math.cos(yawRef.current), 0, -Math.sin(yawRef.current))
    camera.position.addScaledVector(forward.current, moveForward * speed * delta)
    camera.position.addScaledVector(right.current, moveRight * speed * delta)
    camera.position.x = MathUtils.clamp(camera.position.x, bounds.minX, bounds.maxX)
    camera.position.z = MathUtils.clamp(camera.position.z, bounds.minZ, bounds.maxZ)
    camera.position.y = eyeHeight
    refreshCenteredInteraction()
  })

  return null
}

function TouchStick({ label, onChange }) {
  const stickRef = useRef(null)
  const activePointer = useRef(null)
  const [knob, setKnob] = useState({ x: 0, y: 0 })

  const update = (event) => {
    const rect = stickRef.current?.getBoundingClientRect()
    if (!rect) return
    const radius = rect.width * 0.34
    const rawX = event.clientX - (rect.left + rect.width / 2)
    const rawY = event.clientY - (rect.top + rect.height / 2)
    const length = Math.hypot(rawX, rawY)
    const scale = length > radius ? radius / length : 1
    const x = rawX * scale
    const y = rawY * scale
    setKnob({ x, y })
    onChange(x / radius, y / radius)
  }

  const release = (event) => {
    if (activePointer.current !== event.pointerId) return
    activePointer.current = null
    setKnob({ x: 0, y: 0 })
    onChange(0, 0)
  }

  return (
    <div className="flex select-none flex-col items-center gap-1.5">
      <div
        ref={stickRef}
        className="relative h-24 w-24 touch-none rounded-full border border-white/25 bg-slate-950/55 shadow-xl backdrop-blur-sm"
        onPointerDown={(event) => {
          event.preventDefault()
          activePointer.current = event.pointerId
          event.currentTarget.setPointerCapture(event.pointerId)
          update(event)
        }}
        onPointerMove={(event) => {
          if (activePointer.current === event.pointerId) update(event)
        }}
        onPointerUp={release}
        onPointerCancel={release}
      >
        <div className="absolute inset-[22%] rounded-full border border-white/10" />
        <div
          className="absolute left-1/2 top-1/2 h-10 w-10 rounded-full border border-cyan-200/60 bg-cyan-400/45 shadow-[0_0_18px_rgba(34,211,238,0.28)]"
          style={{ transform: `translate(calc(-50% + ${knob.x}px), calc(-50% + ${knob.y}px))` }}
        />
      </div>
      <span className="rounded bg-slate-950/70 px-2 py-0.5 text-[9px] font-semibold uppercase tracking-[0.14em] text-white">
        {label}
      </span>
    </div>
  )
}

export function FirstPersonNavigationHud({ inputRef }) {
  const [touchMode, setTouchMode] = useState(false)

  useEffect(() => {
    const media = window.matchMedia('(pointer: coarse)')
    const update = () => setTouchMode(media.matches)
    update()
    media.addEventListener?.('change', update)
    return () => media.removeEventListener?.('change', update)
  }, [])

  return (
    <>
      {!touchMode && (
        <div className="pointer-events-none absolute left-1/2 top-1/2 z-20 h-4 w-4 -translate-x-1/2 -translate-y-1/2">
          <span className="absolute left-1/2 top-0 h-full w-px -translate-x-1/2 bg-white/70" />
          <span className="absolute left-0 top-1/2 h-px w-full -translate-y-1/2 bg-white/70" />
        </div>
      )}

      {touchMode && (
        <>
          <div className="absolute bottom-4 left-4 z-30">
            <TouchStick
              label="Caminar"
              onChange={(x, y) => {
                inputRef.current.moveX = x
                inputRef.current.moveY = -y
              }}
            />
          </div>
          <div className="absolute bottom-4 right-4 z-30">
            <TouchStick
              label="Mirar"
              onChange={(x, y) => {
                inputRef.current.lookX = x
                inputRef.current.lookY = y
              }}
            />
          </div>
        </>
      )}

      <div className="absolute bottom-3 left-1/2 z-30 flex -translate-x-1/2 items-center gap-2">
        {!touchMode && (
          <div className="pointer-events-none border border-white/10 bg-slate-950/80 px-3 py-1.5 text-[9px] text-slate-200 backdrop-blur-sm">
            Flechas/WASD para caminar · clic y mouse para mirar · Esc libera el cursor
          </div>
        )}
        <button
          type="button"
          className="rounded-md border border-white/20 bg-slate-950/85 px-2.5 py-1.5 text-[9px] font-semibold uppercase tracking-[0.1em] text-white shadow-lg backdrop-blur-sm hover:bg-slate-800"
          onClick={() => { inputRef.current.resetRequested = true }}
        >
          Reiniciar vista
        </button>
      </div>
    </>
  )
}
