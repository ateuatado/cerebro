/* Cerebro — review-workspace.js — Workspace Interativo de Transcrição Histórica (Spec 7, 8 & 11) */
/* Assets locais, sem inline, sem CDN — AGENTS.md */

window.deleteDocumentFromWorkspace = (docId, docName) => {
	if (
		!confirm(
			`Deseja APAGAR DEFINITIVAMENTE o documento:\n"${docName}"\n\nIsso apagará o arquivo físico no servidor e todas as conexões geradas por ele no grafo!`,
		)
	) {
		return;
	}

	const baseUrl = (window.BASE_URL || "/").replace(/\/+$/, "") + "/";
	fetch(baseUrl + `documentos/${docId}/deletar`, {
		method: "POST",
		headers: { "X-Requested-With": "XMLHttpRequest" },
	})
		.then((r) => r.json())
		.then((data) => {
			if (data.success) {
				alert(data.message);
				window.location.href = baseUrl + "documentos/pendentes";
			} else {
				alert("Erro ao excluir documento: " + (data.error || "Falha."));
			}
		})
		.catch(() => alert("Erro de comunicação com o servidor."));
};

/** Vocabulário controlado carregado da API (Spec 11) */
let _vocabulary = {};

function loadVocabulary() {
	const base = (window.BASE_URL || "/").replace(/\/+$/, "") + "/";
	return fetch(base + "api/vocabulario-atributos", {
		headers: { "X-Requested-With": "XMLHttpRequest" },
	})
		.then((r) => r.json())
		.then((data) => {
			if (data.success) _vocabulary = data.vocabulary || {};
		})
		.catch(() => console.warn("Vocabulário não carregado."));
}

document.addEventListener("DOMContentLoaded", () => {
	loadVocabulary();

	const container = document.getElementById("workspace-container");
	if (!container) return;

	const docId = parseInt(container.dataset.docId, 10);
	const totalPages = parseInt(container.dataset.totalPages, 10) || 1;

	const imgViewer = document.getElementById("pageImageViewer");
	const wrapper = document.getElementById("imageCanvasWrapper");
	const spinner = document.getElementById("imageLoadingSpinner");
	const cropOverlay = document.getElementById("cropOverlay");
	const currentPageEl = document.getElementById("currentPageNum");
	const textarea = document.getElementById("transcriptionTextarea");
	const charBadge = document.getElementById("charCountBadge");

	const btnPrevPage = document.getElementById("btnPrevPage");
	const btnNextPage = document.getElementById("btnNextPage");
	const btnRotateCcw = document.getElementById("btnRotateCcw");
	const btnRotateCw = document.getElementById("btnRotateCw");
	const btnRotate180 = document.getElementById("btnRotate180");
	const btnToggleCrop = document.getElementById("btnToggleCrop");
	const btnExtractCrop = document.getElementById("btnExtractCrop");
	const btnExtractRegionEnts = document.getElementById(
		"btnExtractRegionEntities",
	);
	const btnSaveText = document.getElementById("btnSaveWorkspaceText");
	const btnExtractFull = document.getElementById("btnExtractPageFullIa");

	// Elementos da Modal (Spec 8)
	const modalEl = document.getElementById("modalRegionEntities");
	let regionModal = null;
	if (modalEl && typeof bootstrap !== "undefined") {
		regionModal = new bootstrap.Modal(modalEl);
	}
	const modalTranscript = document.getElementById("modalRegionTranscript");
	const modalEntitiesList = document.getElementById("modalRegionEntitiesList");
	const modalRelsList = document.getElementById("modalRegionRelsList");
	const btnConfirmEntities = document.getElementById(
		"btnConfirmRegionEntities",
	);

	let currentPage = 1;
	let isCropMode = false;
	let isMouseDown = false;
	let startX = 0,
		startY = 0;
	let cropCoords = null;
	let lastRegionResult = null;
	let hasActiveSelection = false;

	function getBaseUrl() {
		return (window.BASE_URL || "/").replace(/\/+$/, "") + "/";
	}

	// Gerador de Recorte Base64 via HTML5 Canvas (Infalível)
	// Usa coordenadas normalizadas (proporção da resolução natural) para ser robusto
	// a mudanças de tamanho de exibição da imagem
	function getCroppedBase64() {
		if (!cropCoords || !imgViewer || !imgViewer.naturalWidth) return null;

		try {
			const naturalW = imgViewer.naturalWidth;
			const naturalH = imgViewer.naturalHeight;

			// Se temos coordenadas normalizadas (pct_), usa resolução natural diretamente
			if (cropCoords.pct_w && cropCoords.pct_h) {
				const realX = Math.round(cropCoords.pct_x * naturalW);
				const realY = Math.round(cropCoords.pct_y * naturalH);
				const realW = Math.max(10, Math.round(cropCoords.pct_w * naturalW));
				const realH = Math.max(10, Math.round(cropCoords.pct_h * naturalH));

				const canvas = document.createElement("canvas");
				canvas.width = realW;
				canvas.height = realH;

				const ctx = canvas.getContext("2d");
				ctx.drawImage(
					imgViewer,
					realX,
					realY,
					realW,
					realH,
					0,
					0,
					realW,
					realH,
				);

				return canvas.toDataURL("image/jpeg", 0.7);
			}

			// Fallback para coordenadas absolutas da tela
			const displayW = imgViewer.clientWidth;
			const displayH = imgViewer.clientHeight;

			if (!naturalW || !naturalH || !displayW || !displayH) return null;

			const scaleX = naturalW / displayW;
			const scaleY = naturalH / displayH;

			const realX = Math.max(
				0,
				Math.min(Math.round(cropCoords.x * scaleX), naturalW - 10),
			);
			const realY = Math.max(
				0,
				Math.min(Math.round(cropCoords.y * scaleY), naturalH - 10),
			);
			const realW = Math.max(
				10,
				Math.min(Math.round(cropCoords.width * scaleX), naturalW - realX),
			);
			const realH = Math.max(
				10,
				Math.min(Math.round(cropCoords.height * scaleY), naturalH - realY),
			);

			const canvas = document.createElement("canvas");
			canvas.width = realW;
			canvas.height = realH;

			const ctx = canvas.getContext("2d");
			ctx.drawImage(imgViewer, realX, realY, realW, realH, 0, 0, realW, realH);

			return canvas.toDataURL("image/jpeg", 0.7);
		} catch (e) {
			console.warn("Falha ao capturar Canvas Base64:", e);
			return null;
		}
	}

	// 1. Contador de Caracteres
	textarea.addEventListener("input", function () {
		const len = this.value.length;
		charBadge.textContent = len.toLocaleString("pt-BR") + " caracteres";
	});

	// 2. Navegação Paginada
	function loadPage(pageNum) {
		if (pageNum < 1 || pageNum > totalPages) return;
		currentPage = pageNum;
		currentPageEl.textContent = currentPage;

		spinner.classList.remove("d-none");
		resetCrop();

		const imgUrl =
			getBaseUrl() +
			`api/documentos/${docId}/pagina/${currentPage}/imagem?t=` +
			Date.now();
		imgViewer.src = imgUrl;
	}

	imgViewer.addEventListener("load", () => spinner.classList.add("d-none"));
	imgViewer.addEventListener("error", () => spinner.classList.add("d-none"));

	btnPrevPage.addEventListener("click", () => loadPage(currentPage - 1));
	btnNextPage.addEventListener("click", () => loadPage(currentPage + 1));

	// 3. Rotação Física da Imagem no Servidor + Re-OCR
	function handleRotation(degrees) {
		spinner.classList.remove("d-none");
		resetCrop();

		const formData = new FormData();
		formData.append("degrees", degrees);

		// Incluir CSRF token se disponível
		const csrfMeta = document.querySelector('meta[name="csrf-token"]');
		if (csrfMeta) {
			const tokenName = csrfMeta.dataset.name || "csrf_test_name";
			formData.append(tokenName, csrfMeta.content);
		}

		fetch(
			getBaseUrl() + `api/documentos/${docId}/pagina/${currentPage}/girar`,
			{
				method: "POST",
				body: formData,
				headers: { "X-Requested-With": "XMLHttpRequest" },
			},
		)
			.then((r) => {
				if (!r.ok) throw new Error("HTTP " + r.status);
				return r.json();
			})
			.then((data) => {
				spinner.classList.add("d-none");
				if (data.success) {
					loadPage(currentPage);
					if (data.ocrText && data.ocrText.trim().length > 0) {
						if (
							confirm(
								"A rotação gerou uma nova leitura OCR. Deseja anexar esse texto ao editor?",
							)
						) {
							textarea.value +=
								"\n\n--- OCR PÁGINA " +
								currentPage +
								" (Orientação Corrigida) ---\n" +
								data.ocrText;
							textarea.dispatchEvent(new Event("input"));
						}
					}
				} else {
					alert("Erro na rotação: " + (data.error || "Falha."));
				}
			})
			.catch(() => {
				spinner.classList.add("d-none");
				alert(
					"Erro de comunicação ao rotacionar imagem. Verifique se o Python/PIL está instalado no servidor.",
				);
			});
	}

	btnRotateCw.addEventListener("click", () => handleRotation(90));
	btnRotateCcw.addEventListener("click", () => handleRotation(270));
	btnRotate180.addEventListener("click", () => handleRotation(180));

	// 4. Desenho de Seleção por Região (Crop Tool)
	btnToggleCrop.addEventListener("click", () => {
		isCropMode = !isCropMode;
		if (isCropMode) {
			btnToggleCrop.classList.replace("btn-outline-primary", "btn-primary");
			wrapper.classList.add("crop-mode");
		} else {
			btnToggleCrop.classList.replace("btn-primary", "btn-outline-primary");
			wrapper.classList.remove("crop-mode");
			resetCrop();
		}
	});

	// Botão para limpar a seleção (sem sair do modo crop)
	// Clicar novamente no "Recortar Região" enquanto em modo crop limpa seleção
	// Duplo clique no overlay também limpa
	wrapper.addEventListener("dblclick", () => {
		if (isCropMode && hasActiveSelection) {
			resetCrop();
		}
	});

	wrapper.addEventListener("mousedown", (e) => {
		if (!isCropMode) return;
		// Prevenir comportamento padrão de drag da imagem
		e.preventDefault();

		// Se já houver seleção ativa, não reiniciar o arrasto
		if (hasActiveSelection) return;

		const rect = imgViewer.getBoundingClientRect();

		if (
			e.clientX < rect.left ||
			e.clientX > rect.right ||
			e.clientY < rect.top ||
			e.clientY > rect.bottom
		) {
			return;
		}

		isMouseDown = true;
		startX = e.clientX - rect.left;
		startY = e.clientY - rect.top;

		cropOverlay.style.left = imgViewer.offsetLeft + startX + "px";
		cropOverlay.style.top = imgViewer.offsetTop + startY + "px";
		cropOverlay.style.width = "0px";
		cropOverlay.style.height = "0px";
		cropOverlay.classList.remove("d-none");
		btnExtractCrop.classList.add("d-none");
		if (btnExtractRegionEnts) btnExtractRegionEnts.classList.add("d-none");
	});

	wrapper.addEventListener("mousemove", (e) => {
		if (!isCropMode || !isMouseDown) return;
		// Prevenir comportamento padrão de seleção/arraste
		e.preventDefault();

		// Se já houver seleção ativa, não alterar
		if (hasActiveSelection) return;

		const rect = imgViewer.getBoundingClientRect();
		const currentX = Math.max(0, Math.min(e.clientX - rect.left, rect.width));
		const currentY = Math.max(0, Math.min(e.clientY - rect.top, rect.height));

		const width = Math.abs(currentX - startX);
		const height = Math.abs(currentY - startY);
		const left = Math.min(startX, currentX);
		const top = Math.min(startY, currentY);

		cropOverlay.style.left = imgViewer.offsetLeft + left + "px";
		cropOverlay.style.top = imgViewer.offsetTop + top + "px";
		cropOverlay.style.width = width + "px";
		cropOverlay.style.height = height + "px";

		// Armazenar coordenadas normalizadas pela resolução natural da imagem
		const naturalW = imgViewer.naturalWidth;
		const naturalH = imgViewer.naturalHeight;
		const displayW = rect.width;
		const displayH = rect.height;

		const scaleX = naturalW / displayW;
		const scaleY = naturalH / displayH;

		cropCoords = {
			x: left,
			y: top,
			width: width,
			height: height,
			canvas_w: rect.width,
			canvas_h: rect.height,
			// Coordenadas em proporção da resolução natural (0-1)
			pct_x: left / displayW,
			pct_y: top / displayH,
			pct_w: width / displayW,
			pct_h: height / displayH,
			naturalW: naturalW,
			naturalH: naturalH,
		};
	});

	document.addEventListener("mouseup", () => {
		if (
			isMouseDown &&
			cropCoords &&
			cropCoords.width > 15 &&
			cropCoords.height > 15
		) {
			btnExtractCrop.classList.remove("d-none");
			if (btnExtractRegionEnts) btnExtractRegionEnts.classList.remove("d-none");
			hasActiveSelection = true;
		}
		isMouseDown = false;
	});

	function resetCrop() {
		cropOverlay.classList.add("d-none");
		btnExtractCrop.classList.add("d-none");
		if (btnExtractRegionEnts) btnExtractRegionEnts.classList.add("d-none");
		cropCoords = null;
		hasActiveSelection = false;
	}

	// 5. Extração por IA do Texto Recortado
	btnExtractCrop.addEventListener("click", () => {
		if (!cropCoords) return;

		btnExtractCrop.disabled = true;
		btnExtractCrop.innerHTML =
			'<span class="spinner-border spinner-border-sm me-1"></span> Extraindo Texto...';

		const formData = new FormData();

		// Sempre enviar a imagem base64 (mais confiável que coordenadas)
		const base64Crop = getCroppedBase64();
		if (base64Crop) {
			formData.append("crop_image_base64", base64Crop);
		} else {
			// Fallback: coordenadas normalizadas
			formData.append("x", Math.round(cropCoords.x));
			formData.append("y", Math.round(cropCoords.y));
			formData.append("width", Math.round(cropCoords.width));
			formData.append("height", Math.round(cropCoords.height));
			formData.append("canvas_w", Math.round(cropCoords.canvas_w));
			formData.append("canvas_h", Math.round(cropCoords.canvas_h));
		}

		// Incluir CSRF token se disponível
		const csrfMeta = document.querySelector('meta[name="csrf-token"]');
		if (csrfMeta) {
			const tokenName = csrfMeta.dataset.name || "csrf_test_name";
			formData.append(tokenName, csrfMeta.content);
		}

		fetch(
			getBaseUrl() +
				`api/documentos/${docId}/pagina/${currentPage}/extrair-regiao`,
			{
				method: "POST",
				body: formData,
				headers: { "X-Requested-With": "XMLHttpRequest" },
			},
		)
			.then((r) => {
				if (!r.ok) throw new Error("HTTP " + r.status);
				return r.json();
			})
			.then((data) => {
				btnExtractCrop.disabled = false;
				btnExtractCrop.innerHTML =
					'<i class="bi bi-cpu me-1"></i> Extrair Texto (IA)';

				if (data.success && data.text) {
					const extractedText = data.text.trim();
					textarea.value +=
						(textarea.value ? "\n\n" : "") +
						`[TRECHO RECORTADO DA PÁGINA ${currentPage}]:\n` +
						extractedText;
					textarea.dispatchEvent(new Event("input"));
					alert(
						"Transcrição da região extraída e anexada ao editor com sucesso!",
					);
					resetCrop();
				} else {
					alert(
						"Erro na extração da região: " +
							(data.error || "Nenhum texto reconhecido."),
					);
				}
			})
			.catch(() => {
				btnExtractCrop.disabled = false;
				btnExtractCrop.innerHTML =
					'<i class="bi bi-cpu me-1"></i> Extrair Texto (IA)';
				alert(
					"Falha de conexão ao extrair região. Verifique o console do navegador para detalhes.",
				);
			});
	});

	// 6. Spec 8: Extração de Entidades & Grafo da Região em 1-Clique
	if (btnExtractRegionEnts) {
		btnExtractRegionEnts.addEventListener("click", () => {
			if (!cropCoords) return;

			btnExtractRegionEnts.disabled = true;
			btnExtractRegionEnts.innerHTML =
				'<span class="spinner-border spinner-border-sm me-1"></span> Extraindo Entidades...';

			const formData = new FormData();

			const base64Crop = getCroppedBase64();
			if (base64Crop) {
				formData.append("crop_image_base64", base64Crop);
			} else {
				formData.append("x", Math.round(cropCoords.x));
				formData.append("y", Math.round(cropCoords.y));
				formData.append("width", Math.round(cropCoords.width));
				formData.append("height", Math.round(cropCoords.height));
				formData.append("canvas_w", Math.round(cropCoords.canvas_w));
				formData.append("canvas_h", Math.round(cropCoords.canvas_h));
			}

			const csrfMeta = document.querySelector('meta[name="csrf-token"]');
			if (csrfMeta) {
				const tokenName = csrfMeta.dataset.name || "csrf_test_name";
				formData.append(tokenName, csrfMeta.content);
			}

			fetch(
				getBaseUrl() +
					`api/documentos/${docId}/pagina/${currentPage}/extrair-entidades-regiao`,
				{
					method: "POST",
					body: formData,
					headers: { "X-Requested-With": "XMLHttpRequest" },
				},
			)
				.then((r) => {
					if (!r.ok) throw new Error("HTTP " + r.status);
					return r.json();
				})
				.then((data) => {
					btnExtractRegionEnts.disabled = false;
					btnExtractRegionEnts.innerHTML =
						'<i class="bi bi-diagram-3-fill me-1"></i> ✨ Extrair Entidades (IA)';

					if (data.success) {
						lastRegionResult = data;
						renderRegionEntitiesModal(data);
						if (regionModal) regionModal.show();
					} else {
						alert(
							"Erro ao extrair entidades da região: " +
								(data.error || "Falha."),
						);
					}
				})
				.catch(() => {
					btnExtractRegionEnts.disabled = false;
					btnExtractRegionEnts.innerHTML =
						'<i class="bi bi-diagram-3-fill me-1"></i> ✨ Extrair Entidades (IA)';
					alert("Erro de conexão ao extrair entidades da área selecionada.");
				});
		});
	}

	function renderRegionEntitiesModal(data) {
		modalTranscript.value = data.transcription || "";
		modalEntitiesList.innerHTML = "";
		modalRelsList.innerHTML = "";

		const entities = data.entities || [];
		const rels = data.relationships || [];

		if (entities.length === 0) {
			modalEntitiesList.innerHTML =
				'<div class="text-muted" style="font-size:.8125rem">Nenhuma entidade identificada especificamente neste trecho.</div>';
		} else {
			entities.forEach((ent, idx) => {
				modalEntitiesList.appendChild(buildEntityCard(ent, idx));
			});
		}

		if (rels.length === 0) {
			modalRelsList.innerHTML =
				'<li class="list-group-item bg-transparent text-muted px-0">Nenhuma relação detectada entre entidades neste trecho.</li>';
		} else {
			rels.forEach((rel) => {
				const li = document.createElement("li");
				li.className =
					"list-group-item bg-transparent text-subtle px-0 d-flex align-items-center justify-content-between";
				li.innerHTML = `
                    <div>
                        <strong>${escHtml(rel.source_name)}</strong> 
                        <span class="badge bg-outline-primary mx-1" style="border:1px solid var(--cbr-primary);color:var(--cbr-primary)">${escHtml(rel.relationship_type)}</span> 
                        <strong>${escHtml(rel.target_name)}</strong>
                    </div>
                    <span class="badge bg-secondary">${Math.round((rel.confidence || 0.85) * 100)}%</span>
                `;
				modalRelsList.appendChild(li);
			});
		}
	}

	/** Constrói card editável de entidade (tipo + atributos) */
	function buildEntityCard(ent, idx) {
		const VALID_TYPES = ["person", "location", "event", "document"];
		const typeBadge = { person: "bg-primary", location: "bg-success", event: "bg-warning text-dark", document: "bg-secondary" };
		const currentType = VALID_TYPES.includes(ent.type) ? ent.type : "person";
		const isInvalidType = !VALID_TYPES.includes(ent.type);

		const card = document.createElement("div");
		card.className = "p-2 border rounded entity-curation-card";
		card.style.cssText = `background:var(--cbr-surface-2);${isInvalidType ? 'border-color:#f59e0b!important;' : ''}`;
		card.dataset.idx = idx;

		// Cabeçalho: checkbox + nome + dropdown de tipo
		const header = document.createElement("div");
		header.className = "d-flex align-items-center gap-2 mb-2";
		header.innerHTML = `
			<input class="form-check-input chk-region-entity mt-0" type="checkbox" id="chkEnt_${idx}" value="${idx}" checked>
			<label class="fw-bold flex-grow-1" for="chkEnt_${idx}" style="font-size:.875rem;cursor:pointer">${escHtml(ent.name)}</label>
			${isInvalidType ? `<span class="badge bg-warning text-dark" title="Tipo '${escHtml(ent.type)}' inválido — selecione um tipo válido">⚠ tipo inválido</span>` : ''}
			<select class="form-select form-select-sm entity-type-select" style="width:auto;font-size:.8125rem;background:var(--cbr-surface-1);color:var(--cbr-text);border-color:var(--cbr-border)">
				${VALID_TYPES.map(t => `<option value="${t}" ${t === currentType ? 'selected' : ''}>${t}</option>`).join('')}
			</select>
		`;
		card.appendChild(header);

		// Corpo: atributos editáveis
		const attrsContainer = document.createElement("div");
		attrsContainer.className = "entity-attrs-container ms-4";

		const attrs = (ent.attributes && typeof ent.attributes === "object") ? ent.attributes : {};

		// Renderiza atributos existentes
		Object.entries(attrs).forEach(([key, value]) => {
			attrsContainer.appendChild(buildAttrRow(currentType, key, String(value ?? '')));
		});

		// Botão Adicionar Atributo
		const addBtn = document.createElement("button");
		addBtn.type = "button";
		addBtn.className = "btn btn-outline-secondary btn-sm mt-1";
		addBtn.style.fontSize = ".75rem";
		addBtn.innerHTML = '<i class="bi bi-plus-circle me-1"></i> Adicionar atributo';
		addBtn.addEventListener("click", () => {
			const typeSelect = card.querySelector(".entity-type-select");
			attrsContainer.insertBefore(buildAttrRow(typeSelect.value, '', ''), addBtn);
		});

		// Atualiza chaves do vocabulário ao trocar tipo
		const typeSelect = header.querySelector(".entity-type-select");
		typeSelect.addEventListener("change", () => {
			card.querySelectorAll(".attr-key-select").forEach(sel => {
				updateKeyOptions(sel, typeSelect.value);
			});
		});

		attrsContainer.appendChild(addBtn);
		card.appendChild(attrsContainer);
		return card;
	}

	/** Constrói uma linha de atributo (chave-select + valor-input + botão remover) */
	function buildAttrRow(entityType, key, value) {
		const row = document.createElement("div");
		row.className = "d-flex align-items-center gap-1 mb-1 attr-row";

		const keySelect = document.createElement("select");
		keySelect.className = "form-select form-select-sm attr-key-select";
		keySelect.style.cssText = "width:42%;font-size:.75rem;background:var(--cbr-surface-1);color:var(--cbr-text);border-color:var(--cbr-border)";
		updateKeyOptions(keySelect, entityType, key);
		keySelect.addEventListener("change", () => {
			const customInput = row.querySelector(".attr-key-custom");
			if (keySelect.value === "__new__") {
				customInput.style.display = "";
				customInput.focus();
			} else {
				customInput.style.display = "none";
			}
		});

		const customInput = document.createElement("input");
		customInput.type = "text";
		customInput.className = "form-control form-control-sm attr-key-custom";
		customInput.placeholder = "nova chave...";
		customInput.style.cssText = `display:${key && !vocabHasKey(entityType, key) ? '' : 'none'};font-size:.75rem;width:35%;border-style:dashed;`;
		if (key && !vocabHasKey(entityType, key)) customInput.value = key;

		const valueInput = document.createElement("input");
		valueInput.type = "text";
		valueInput.className = "form-control form-control-sm attr-value-input";
		valueInput.value = value;
		valueInput.placeholder = "valor...";
		valueInput.style.cssText = "font-size:.75rem;flex:1";

		const removeBtn = document.createElement("button");
		removeBtn.type = "button";
		removeBtn.className = "btn btn-sm btn-outline-danger";
		removeBtn.style.fontSize = ".75rem";
		removeBtn.innerHTML = '<i class="bi bi-x"></i>';
		removeBtn.addEventListener("click", () => row.remove());

		row.appendChild(keySelect);
		row.appendChild(customInput);
		row.appendChild(valueInput);
		row.appendChild(removeBtn);
		return row;
	}

	/** Popula as options do select de chave baseado no tipo e no vocabulário */
	function updateKeyOptions(selectEl, entityType, selectedKey = null) {
		const vocabForType = (_vocabulary[entityType] || []);
		const currentVal = selectedKey !== null ? selectedKey : selectEl.value;
		selectEl.innerHTML = '';

		// Placeholder vazio
		const placeholder = document.createElement("option");
		placeholder.value = "";
		placeholder.text = "-- chave --";
		placeholder.disabled = true;
		if (!currentVal) placeholder.selected = true;
		selectEl.appendChild(placeholder);

		vocabForType.forEach(v => {
			const opt = document.createElement("option");
			opt.value = v.key;
			opt.text = v.key + (v.label ? ` — ${v.label}` : '');
			opt.title = v.desc || '';
			if (v.key === currentVal) opt.selected = true;
			selectEl.appendChild(opt);
		});

		// Opção para chave nova
		const newOpt = document.createElement("option");
		newOpt.value = "__new__";
		newOpt.text = "+ nova chave...";
		if (currentVal && !vocabHasKey(entityType, currentVal)) newOpt.selected = true;
		selectEl.appendChild(newOpt);
	}

	function vocabHasKey(entityType, key) {
		return (_vocabulary[entityType] || []).some(v => v.key === key);
	}

	// 7. Confirmação das Entidades Selecionadas no Grafo (Spec 8 + Spec 11)
	if (btnConfirmEntities) {
		btnConfirmEntities.addEventListener("click", () => {
			if (!lastRegionResult) return;

			// Coleta entidades dos cards editáveis
			const selectedEntities = [];
			document.querySelectorAll(".entity-curation-card").forEach((card) => {
				const chk = card.querySelector(".chk-region-entity");
				if (!chk || !chk.checked) return;

				const idx = parseInt(card.dataset.idx, 10);
				const originalEnt = lastRegionResult.entities[idx] || {};

				const type = card.querySelector(".entity-type-select")?.value || "person";

				// Coleta atributos das linhas editáveis
				const attributes = {};
				card.querySelectorAll(".attr-row").forEach((row) => {
					const keySelect = row.querySelector(".attr-key-select");
					const customKey = row.querySelector(".attr-key-custom");
					const valueInput = row.querySelector(".attr-value-input");

					let key = keySelect?.value || "";
					if (key === "__new__") key = customKey?.value?.trim() || "";
					const value = valueInput?.value?.trim() || "";

					if (key && value) attributes[key] = value;
				});

				selectedEntities.push({
					name: originalEnt.name,
					type,
					attributes,
				});
			});

			if (selectedEntities.length === 0) {
				alert("Selecione ao menos uma entidade para adicionar ao Grafo.");
				return;
			}

			btnConfirmEntities.disabled = true;
			btnConfirmEntities.innerHTML =
				'<span class="spinner-border spinner-border-sm me-1"></span> Salvando no Grafo...';

			const payload = {
				transcription: modalTranscript.value,
				entities: selectedEntities,
				relationships: lastRegionResult.relationships || [],
			};

			fetch(
				getBaseUrl() + `api/documentos/${docId}/confirmar-entidades-regiao`,
				{
					method: "POST",
					headers: {
						"Content-Type": "application/json",
						"X-Requested-With": "XMLHttpRequest",
					},
					body: JSON.stringify(payload),
				},
			)
				.then((r) => r.json())
				.then((data) => {
					btnConfirmEntities.disabled = false;
					btnConfirmEntities.innerHTML =
						'<i class="bi bi-check-circle-fill"></i> Confirmar e Adicionar ao Grafo';

					if (data.success) {
						if (regionModal) regionModal.hide();
						alert(
							data.message ||
								"Entidades e relações adicionadas ao Grafo com sucesso!",
						);

						if (modalTranscript.value.trim()) {
							textarea.value +=
								(textarea.value ? "\n\n" : "") +
								`[TRANSCRIÇÃO HTR RECORTE PÁG. ${currentPage}]:\n` +
								modalTranscript.value.trim();
							textarea.dispatchEvent(new Event("input"));
						}
						resetCrop();
					} else {
						alert("Erro ao gravar entidades: " + (data.error || "Falha."));
					}
				})
				.catch(() => {
					btnConfirmEntities.disabled = false;
					btnConfirmEntities.innerHTML =
						'<i class="bi bi-check-circle-fill"></i> Confirmar e Adicionar ao Grafo';
					alert("Falha de conexão ao salvar entidades.");
				});
		});
	}

	// 8. Salvar Transcrição no Repositório
	btnSaveText.addEventListener("click", () => {
		const text = textarea.value;
		if (!text.trim()) {
			alert("O texto da transcrição não pode estar vazio.");
			return;
		}

		btnSaveText.disabled = true;
		btnSaveText.innerHTML =
			'<span class="spinner-border spinner-border-sm me-1"></span> Salvando no Repositório...';

		const formData = new FormData();
		formData.append("conteudo_transcrito", text);

		fetch(
			getBaseUrl() +
				`api/documentos/${docId}/pagina/${currentPage}/salvar-texto`,
			{
				method: "POST",
				body: formData,
				headers: { "X-Requested-With": "XMLHttpRequest" },
			},
		)
			.then((r) => r.json())
			.then((data) => {
				btnSaveText.disabled = false;
				btnSaveText.innerHTML =
					'<i class="bi bi-save-fill"></i> Salvar Transcrição no Repositório';

				if (data.success) {
					alert(
						data.message || "Transcrição salva com sucesso no repositório!",
					);
				} else {
					alert("Erro ao salvar transcrição: " + (data.error || "Falha."));
				}
			})
			.catch(() => {
				btnSaveText.disabled = false;
				btnSaveText.innerHTML =
					'<i class="bi bi-save-fill"></i> Salvar Transcrição no Repositório';
				alert("Erro de rede ao salvar a transcrição.");
			});
	});

	// 9. Extrair Página Completa por IA
	if (btnExtractFull) {
		btnExtractFull.addEventListener("click", () => {
			if (
				!confirm(
					"Deseja executar a extração de entidades e relações por IA sobre o texto do documento?",
				)
			) {
				return;
			}

			btnExtractFull.disabled = true;
			btnExtractFull.innerHTML =
				'<span class="spinner-border spinner-border-sm me-1"></span> Processando por IA...';

			fetch(getBaseUrl() + `api/documentos/pendentes/extrair/${docId}`, {
				method: "POST",
				headers: { "X-Requested-With": "XMLHttpRequest" },
			})
				.then((r) => r.json())
				.then((data) => {
					btnExtractFull.disabled = false;
					btnExtractFull.innerHTML =
						'<i class="bi bi-cpu-fill"></i> Extrair Página Inteira com IA';

					if (data.success) {
						alert(
							`Extração por IA concluída!\n\nEntidades no Grafo: ${data.entitiesExtracted}\nRelações no Grafo: ${data.relsExtracted}`,
						);
					} else {
						alert("Erro na extração: " + (data.error || "Falha."));
					}
				})
				.catch(() => {
					btnExtractFull.disabled = false;
					btnExtractFull.innerHTML =
						'<i class="bi bi-cpu-fill"></i> Extrair Página Inteira com IA';
					alert("Erro de conexão com o servidor.");
				});
		});
	}

	function escHtml(str) {
		return String(str || "").replace(
			/[&<>"']/g,
			(c) =>
				({
					"&": "&amp;",
					"<": "&lt;",
					">": "&gt;",
					'"': "&quot;",
					"'": "&#39;",
				})[c],
		);
	}
});
